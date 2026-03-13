<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAttendanceJob;
use App\Models\Attendance;
use App\Models\AttendanceToken;
use App\Models\LectureSession;
use App\Models\Student;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
class AttendanceController extends Controller
{
    /**
     * Display the attendance form.
     */
    public function index()
    {
        // Check if there's an active session in the URL
        $sessionId = request('session');

        if ($sessionId) {
            $session = LectureSession::with(['subject', 'lecturer', 'hall'])
                ->find($sessionId);

            if ($session && $session->status === 'active') {
                // Calculate actual remaining time from server-side
                $remainingSeconds = $this->calculateRemainingSeconds($session);

                return view('student.attendance', [
                    'sessionId' => $session->id,
                    'sessionDetails' => $session,
                    'remainingSeconds' => $remainingSeconds,
                ]);
            }
        }

        return view('student.attendance');
    }

    /**
     * Calculate remaining seconds for OTP validity.
     * Uses qr_started_at (when QR was generated) not created_at.
     * Returns 0 if expired or invalid.
     */
  private function calculateRemainingSeconds(LectureSession $session): int
{
    if ($session->qr_expires_at) {
        $expiresAt = $session->qr_expires_at instanceof Carbon
            ? $session->qr_expires_at
            : Carbon::parse($session->qr_expires_at);

        return max(0, now()->diffInSeconds($expiresAt, false));
    }

    if ($session->qr_started_at) {
        $startedAt = $session->qr_started_at instanceof Carbon
            ? $session->qr_started_at
            : Carbon::parse($session->qr_started_at);

        $expiresAt = $startedAt->copy()->addSeconds((int) ($session->qr_refresh_rate ?? 120));

        return max(0, now()->diffInSeconds($expiresAt, false));
    }

    return 0;
}
private function finalizeExpiredSession(LectureSession $session): void
{
    $session->refresh();

    if (! $session->qr_expires_at) {
        return;
    }

    $expiresAt = $session->qr_expires_at instanceof Carbon
        ? $session->qr_expires_at
        : Carbon::parse($session->qr_expires_at);

    if (now()->greaterThanOrEqualTo($expiresAt) && ! $session->qr_expired) {
        $session->update([
            'qr_expired' => true,
            'status' => 'completed',
            'actual_end' => $session->actual_end ?? now(),
        ]);

        $session->refresh();
    }
}
    /**
     * Generate and display QR code for a session.
     */
    public function showQr(LectureSession $session)
    {
        // Refresh the session model to get latest data from database
        // This ensures we have the most recent qr_expired status
        $session->refresh();

        // Server-side enforcement: Check if QR has expired based on qr_expires_at timestamp
        // This is the authoritative source of truth - if the time has passed, treat as expired
        if ($session->qr_expires_at && now()->greaterThan($session->qr_expires_at)) {
            // Auto-expire the session if qr_expires_at time has passed but qr_expired wasn't set
            $session->update([
                'qr_expired' => true,
                'status' => 'completed',
                'actual_end' => now(),
            ]);
            $session->refresh();
        }

        // Check if QR code has already expired - never show QR again once expired
        if ($session->qr_expired) {
            return view('teacher.lecture-session-qr', [
                'session' => $session,
                'qr' => null,
                'otp' => null,
                'tokenValue' => null,
                'expired' => true,
            ]);
        }

        if ($session->status !== 'active') {
            abort(403);
        }

        // Clean up expired tokens
        AttendanceToken::where('lecture_session_id', $session->id)
            ->where('expires_at', '<', now())
            ->delete();

        // Check if there's an existing valid token
        $existingToken = AttendanceToken::where('lecture_session_id', $session->id)
            ->where('expires_at', '>=', now())
            ->where('is_used', false)
            ->first();

        // If no valid token exists, generate a new one
        if (! $existingToken) {
            $tokenValue = Str::random(32);
            $expiresAt = now()->addSeconds($session->qr_refresh_rate);

            AttendanceToken::create([
                'lecture_session_id' => $session->id,
                'token_type' => 'qr',
                'token_value' => $tokenValue,
                'expires_at' => $expiresAt,
            ]);

            // Generate new OTP and save QR timing
            $otp = rand(100000, 999999);
            $session->update([
                'session_otp' => $otp,
                'qr_started_at' => now(),
                'qr_expires_at' => $expiresAt,
            ]);
        } else {
            // Reuse existing valid token
            $tokenValue = $existingToken->token_value;
            $otp = $session->session_otp;

            if (empty($otp)) {
                $otp = rand(100000, 999999);
                $session->update(['session_otp' => $otp]);
            }

            // Ensure QR timing is set
            if (! $session->qr_started_at) {
                $session->update([
                    'qr_started_at' => now(),
                    'qr_expires_at' => $existingToken->expires_at,
                ]);
            }
        }

        $tokenData = route('student.attendance.verify.token', ['token' => $tokenValue]);

        $writer = new PngWriter;
        $qrCode = new \Endroid\QrCode\QrCode($tokenData);
        $result = $writer->write($qrCode);
        $qr = $result->getDataUri();

        return view('teacher.lecture-session-qr', [
            'session' => $session,
            'qr' => $qr,
            'otp' => $otp,
            'tokenValue' => $tokenValue,
            'expired' => false,
        ]);

    }

    /**
     * Handle QR scan redirect.
     */
    public function scan(Request $request, LectureSession $session)
    {
        if ($session->status !== 'active') {
            abort(403, __('session.not_active'));
        }

        return redirect()->route('student.attendance.verify.form', [
            'session' => $session->id,
        ]);
    }

    /**
     * Verify token from QR code.
     */
   public function verifyToken($tokenValue)
{
    $token = AttendanceToken::where('token_value', $tokenValue)
        ->where('expires_at', '>=', now())
        ->where('is_used', false)
        ->first();

    if (! $token) {
        abort(403, __('session.token_expired'));
    }

    $session = $token->lectureSession;

    if (! $session) {
        abort(403, __('session.not_found'));
    }

    $this->finalizeExpiredSession($session);

    if ($session->qr_expired || $session->status !== 'active') {
        abort(403, __('session.qr_session_expired'));
    }

    $remainingSeconds = $this->calculateRemainingSeconds($session);

    if ($remainingSeconds <= 0) {
        $this->finalizeExpiredSession($session);
        abort(403, __('session.token_expired'));
    }

    $sessionDetails = LectureSession::with(['subject', 'lecturer', 'hall'])
        ->findOrFail($session->id);

    session(['verify_session' => $session->id]);

    return view('student.attendance', [
        'sessionId' => $session->id,
        'sessionDetails' => $sessionDetails,
        'remainingSeconds' => $remainingSeconds,
    ]);
}

    /**
     * Verify session and display attendance form.
     */
  public function verifySession($sessionId)
{
    $session = LectureSession::with(['subject', 'lecturer', 'hall'])->findOrFail($sessionId);

    $this->finalizeExpiredSession($session);

    if ($session->qr_expired || $session->status !== 'active') {
        abort(403, __('session.qr_session_expired'));
    }

    $remainingSeconds = $this->calculateRemainingSeconds($session);

    if ($remainingSeconds <= 0) {
        $this->finalizeExpiredSession($session);
        abort(403, __('session.token_expired'));
    }

    session(['verify_session' => $sessionId]);

    return view('student.attendance', [
        'sessionId' => $sessionId,
        'sessionDetails' => $session,
        'remainingSeconds' => $remainingSeconds,
    ]);
}

    /**
     * Store attendance - optimized for high concurrency.
     * This method dispatches the job to queue for processing.
     * Falls back to synchronous processing if queue is not available.
     */
    public function store(Request $request, $sessionId)
    {
        $request->validate([
            'student_number' => 'required|string|max:50',
            'otp' => 'required|string|size:6',
        ]);

        // Generate unique request ID for tracking
        $requestId = uniqid('att_', true);

        // Get client info
        $ipAddress = $request->ip();
        $deviceFingerprint = $request->header('X-Device-Fingerprint')
            ?? $request->userAgent()
            ?? null;

        // Check if this student already has a pending request
        $pendingKey = "pending:{$sessionId}:{$request->student_number}";
        if (Cache::has($pendingKey)) {
            return back()->with('error', __('student.already_attending'));
        }

        // Mark as pending for 30 seconds
        Cache::put($pendingKey, true, 30);

        try {
            // Try to dispatch to queue first
            $job = new ProcessAttendanceJob(
                studentNumber: $request->student_number,
                otp: $request->otp,
                sessionId: $sessionId,
                ipAddress: $ipAddress,
                deviceFingerprint: $deviceFingerprint
            );

            // Try to dispatch, catch exception if queue is not available
            try {
                dispatch($job);
            } catch (\Exception $e) {
                // Queue failed, process synchronously as fallback
                Log::warning('Queue dispatch failed, falling back to sync processing', [
                    'error' => $e->getMessage(),
                    'student_number' => $request->student_number,
                    'session_id' => $sessionId,
                ]);

                // Process synchronously
                $result = $job->handle();

                if (! $result['success']) {
                    Cache::forget($pendingKey);

                    return back()->with('error', $result['message']);
                }

                Cache::forget($pendingKey);

                return back()->with('success', $result['message']);
            }

            // Return success immediately - processing is async
            return back()->with('success', __('student.attendance_processing'));

        } catch (\Exception $e) {
            // Remove pending lock on error
            Cache::forget($pendingKey);

            Log::error('Attendance processing error', [
                'error' => $e->getMessage(),
                'student_number' => $request->student_number,
                'session_id' => $sessionId,
            ]);

            return back()->with('error', __('student.connection_error'));
        }
    }

    /**
     * Check attendance status - AJAX endpoint for real-time status.
     * This is called by the frontend to check if attendance was processed.
     */
public function checkStatus(Request $request, $sessionId): JsonResponse
{
    $request->validate([
        'student_number' => 'required|string',
    ]);

    $studentNumber = $request->student_number;

    $session = LectureSession::find($sessionId);

    if (! $session) {
        return response()->json([
            'success' => false,
            'status' => 'failed',
            'message' => __('session.not_found'),
            'remaining_seconds' => 0,
        ], 404);
    }

    $this->finalizeExpiredSession($session);

    $remainingSeconds = $this->calculateRemainingSeconds($session);

    if ($session->qr_expired || $session->status !== 'active' || $remainingSeconds <= 0) {
        return response()->json([
            'success' => false,
            'status' => 'failed',
            'message' => __('session.token_expired'),
            'remaining_seconds' => 0,
        ]);
    }

    $student = Student::where('student_number', $studentNumber)->first();

    if ($student) {
        $attendance = Attendance::where('student_id', $student->id)
            ->where('lecture_session_id', $sessionId)
            ->first();

        if ($attendance) {
            Cache::forget("pending:{$sessionId}:{$studentNumber}");

            return response()->json([
                'success' => true,
                'status' => 'recorded',
                'message' => __('student.attendance_recorded'),
                'attendance_time' => $attendance->attendance_time->toIso8601String(),
                'remaining_seconds' => $remainingSeconds,
            ]);
        }
    }

    $failedKey = "failed:{$sessionId}:{$studentNumber}";
    $failedReason = Cache::get($failedKey);

    if ($failedReason) {
        Cache::forget($failedKey);

        return response()->json([
            'success' => false,
            'status' => 'failed',
            'message' => $failedReason,
            'remaining_seconds' => $remainingSeconds,
        ]);
    }

    return response()->json([
        'success' => true,
        'status' => 'processing',
        'message' => __('student.checking'),
        'remaining_seconds' => $remainingSeconds,
    ]);
}

    /**
     * Synchronous attendance check - for when queue is not available.
     * This provides immediate response for smaller scale usage.
     */
   public function storeSync(Request $request, $sessionId)
{
    $request->validate([
        'student_number' => 'required|string|max:50',
        'otp' => 'required|string|size:6',
    ]);

    $session = LectureSession::with('subject')->find($sessionId);

    if (! $session) {
        return back()->with('error', __('session.not_found'));
    }

    $this->finalizeExpiredSession($session);

    if ($session->qr_expired || $session->status !== 'active') {
        return back()->with('error', __('session.token_expired'));
    }

    $remainingSeconds = $this->calculateRemainingSeconds($session);

    if ($remainingSeconds <= 0) {
        $this->finalizeExpiredSession($session);

        return back()->with('error', __('session.token_expired'));
    }

    if ((string) $session->session_otp !== (string) $request->otp) {
        return back()->with('error', __('student.invalid_otp'));
    }

    $student = Student::where('student_number', $request->student_number)->first();

    if (! $student) {
        return back()->with('error', __('student.not_found'));
    }

    if ($session->subject_id) {
        $isEnrolled = \DB::table('enrollments')
            ->where('student_id', $student->id)
            ->where('subject_id', $session->subject_id)
            ->exists();

        if (! $isEnrolled) {
            return back()->with('error', __('student.not_enrolled_in_subject'));
        }
    }

    $exists = Attendance::where('student_id', $student->id)
        ->where('lecture_session_id', $sessionId)
        ->exists();

    if ($exists) {
        return back()->with('error', __('student.already_attended'));
    }

    Attendance::create([
        'student_id' => $student->id,
        'lecture_session_id' => $sessionId,
        'attendance_time' => now(),
        'attendance_method' => 'qr_scan',
        'attendance_status' => 'present',
        'ip_address' => $request->ip(),
    ]);

    return back()->with('success', __('student.attendance_recorded'));
}
}
