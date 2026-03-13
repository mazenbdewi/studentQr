<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\LectureSession;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Indicate if this is a synchronous call (for fallback).
     */
    public bool $sync = false;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $studentNumber,
        public string $otp,
        public int $sessionId,
        public ?string $ipAddress = null,
        public ?string $deviceFingerprint = null
    ) {
        $this->onQueue('attendance');
    }

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $startTime = microtime(true);

        try {
            // Validate session first (cached query)
            $session = $this->getCachedSession($this->sessionId);

            if (! $session) {
                $this->storeFailedAttempt(__('session.not_found'));

                return $this->result(false, __('session.not_found'), 'session_not_found');
            }

            // Check session status
            if ($session->status !== 'active') {
                $this->storeFailedAttempt(__('session.not_active'));

                return $this->result(false, __('session.not_active'), 'session_not_active');
            }

            // Check if OTP is valid (with time limit)
            if (! $this->isValidOtp($session)) {
                $this->storeFailedAttempt(__('session.token_expired'));

                return $this->result(false, __('session.token_expired'), 'otp_expired');
            }

            // Verify OTP
            if ($session->session_otp != $this->otp) {
                $this->storeFailedAttempt(__('student.invalid_otp'));

                return $this->result(false, __('student.invalid_otp'), 'invalid_otp');
            }

            // Get student (cached query)
            $student = $this->getCachedStudent($this->studentNumber);

            if (! $student) {
                $this->storeFailedAttempt(__('student.not_found'));

                return $this->result(false, __('student.not_found'), 'student_not_found');
            }

            // Check enrollment if subject exists
            if ($session->subject_id) {
                $isEnrolled = $this->checkEnrollment($student->id, $session->subject_id);
                if (! $isEnrolled) {
                    $this->storeFailedAttempt(__('student.not_enrolled_in_subject'));

                    return $this->result(false, __('student.not_enrolled_in_subject'), 'not_enrolled');
                }
            }

            // Check for duplicate attendance
            $exists = $this->checkDuplicateAttendance($student->id, $this->sessionId);
            if ($exists) {
                $this->storeFailedAttempt(__('student.already_attended'));

                return $this->result(false, __('student.already_attended'), 'duplicate_attendance');
            }

            // Record attendance
            Attendance::create([
                'student_id' => $student->id,
                'lecture_session_id' => $this->sessionId,
                'attendance_time' => now(),
                'attendance_method' => 'qr_scan',
                'attendance_status' => 'present',
                'ip_address' => $this->ipAddress,
                'device_fingerprint' => $this->deviceFingerprint,
            ]);

            // Clear failed attempt cache on success
            $this->clearFailedAttempt();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Attendance processed successfully', [
                'student_id' => $student->id,
                'session_id' => $this->sessionId,
                'duration_ms' => $duration,
            ]);

            return $this->result(true, __('student.attendance_recorded'), 'success');

        } catch (\Exception $e) {
            Log::error('Attendance processing failed', [
                'error' => $e->getMessage(),
                'student_number' => $this->studentNumber,
                'session_id' => $this->sessionId,
            ]);

            $this->storeFailedAttempt(__('student.connection_error'));

            return $this->result(false, __('student.connection_error'), 'error');
        }
    }

    /**
     * Store failed attempt in cache for status checking
     */
    private function storeFailedAttempt(string $reason): void
    {
        $failedKey = "failed:{$this->sessionId}:{$this->studentNumber}";
        cache()->put($failedKey, $reason, 60);
    }

    /**
     * Clear failed attempt cache
     */
    private function clearFailedAttempt(): void
    {
        $failedKey = "failed:{$this->sessionId}:{$this->studentNumber}";
        cache()->forget($failedKey);
    }

    /**
     * Get session with caching
     * Always refresh to get latest data for time-sensitive operations
     */
    private function getCachedSession(int $sessionId): ?LectureSession
    {
        $cacheKey = "session:{$sessionId}";

        return cache()->remember($cacheKey, 300, function () use ($sessionId) {
            $session = LectureSession::with('subject')
                ->find($sessionId);
            
            // Always refresh to get latest timing data
            if ($session) {
                $session->refresh();
            }
            
            return $session;
        });
    }

    /**
     * Get student with caching
     */
    private function getCachedStudent(string $studentNumber): ?Student
    {
        $cacheKey = "student:{$studentNumber}";

        return cache()->remember($cacheKey, 3600, function () use ($studentNumber) {
            return Student::where('student_number', $studentNumber)->first();
        });
    }

    /**
     * Check if OTP is still valid based on time
     * Uses qr_started_at (when QR was generated) not created_at (when session was created)
     * This method is now more lenient - allows submission if session is not permanently expired
     */
    private function isValidOtp(LectureSession $session): bool
    {
        // First check if session is permanently expired
        if ($session->qr_expired) {
            return false;
        }
        
        // OTP is valid for the duration of qr_refresh_rate from when QR was generated
        // If qr_started_at is not set, fall back to qr_expires_at or current time
        $startedAt = $session->qr_started_at ?? $session->qr_expires_at;
        
        // If we have no timing data at all, allow the submission (new session)
        if (!$startedAt) {
            return true;
        }
        
        // Ensure we have a Carbon instance (defensive handling for cached models)
        if (! $startedAt instanceof \Carbon\Carbon) {
            $startedAt = \Carbon\Carbon::parse($startedAt);
        }
        
        $expiresAt = $startedAt->copy()->addSeconds((int) ($session->qr_refresh_rate ?? 120));

        // If time has passed but session is not marked as expired, allow submission
        // This gives students a grace period
        if (now()->greaterThan($expiresAt)) {
            // Only reject if explicitly marked as expired
            return !$session->qr_expired;
        }

        return true;
    }

    /**
     * Check if student is enrolled in the subject
     */
    private function checkEnrollment(int $studentId, int $subjectId): bool
    {
        return \DB::table('enrollments')
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /**
     * Check for duplicate attendance
     */
    private function checkDuplicateAttendance(int $studentId, int $sessionId): bool
    {
        return Attendance::where('student_id', $studentId)
            ->where('lecture_session_id', $sessionId)
            ->exists();
    }

    /**
     * Create standardized result array
     */
    private function result(bool $success, string $message, string $code): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'code' => $code,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Attendance job failed permanently', [
            'student_number' => $this->studentNumber,
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
