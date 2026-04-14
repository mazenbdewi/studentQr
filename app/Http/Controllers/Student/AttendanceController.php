<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceToken;
use App\Models\LectureSession;
use App\Models\Student;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'attendance_verification';

    /**
     * Public attendance entry is intentionally disabled.
     * Students must start from a verified QR token.
     */
    public function index()
    {
        abort(404);
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

    private function invalidateVerificationContext(Request $request): void
    {
        $context = $request->session()->pull(self::VERIFICATION_SESSION_KEY);

        if (! is_array($context)) {
            return;
        }

        $submissionTokenId = (int) ($context['submission_token_id'] ?? 0);

        if ($submissionTokenId > 0) {
            AttendanceToken::whereKey($submissionTokenId)
                ->where('token_type', 'otp')
                ->where('is_used', false)
                ->delete();
        }
    }

    private function createVerificationContext(
        Request $request,
        LectureSession $session,
        AttendanceToken $qrToken,
        int $remainingSeconds
    ): string {
        $this->invalidateVerificationContext($request);

        $request->session()->regenerate();

        $submissionToken = Str::random(64);

        $submissionGrant = AttendanceToken::create([
            'lecture_session_id' => $session->id,
            'token_type' => 'otp',
            'token_value' => $submissionToken,
            'expires_at' => now()->addSeconds(max(1, $remainingSeconds)),
        ]);

        $request->session()->put(self::VERIFICATION_SESSION_KEY, [
            'session_id' => $session->id,
            'submission_token_id' => $submissionGrant->id,
            'submission_token_hash' => hash('sha256', $submissionToken),
            'qr_token_id' => $qrToken->id,
            'qr_token_hash' => hash('sha256', $qrToken->token_value),
        ]);

        return $submissionToken;
    }

    private function resolveVerificationContext(
        Request $request,
        int $sessionId,
        ?string $submittedSubmissionToken = null
    ): ?array {
        $context = $request->session()->get(self::VERIFICATION_SESSION_KEY);

        if (! is_array($context) || (int) ($context['session_id'] ?? 0) !== $sessionId) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        $submissionTokenId = (int) ($context['submission_token_id'] ?? 0);
        $submissionTokenHash = $context['submission_token_hash'] ?? null;
        $qrTokenId = (int) ($context['qr_token_id'] ?? 0);
        $qrTokenHash = $context['qr_token_hash'] ?? null;

        if (
            $submissionTokenId <= 0
            || $qrTokenId <= 0
            || ! is_string($submissionTokenHash)
            || $submissionTokenHash === ''
            || ! is_string($qrTokenHash)
            || $qrTokenHash === ''
        ) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        if ($submittedSubmissionToken !== null && ! hash_equals($submissionTokenHash, hash('sha256', $submittedSubmissionToken))) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        $submissionGrant = AttendanceToken::whereKey($submissionTokenId)
            ->where('lecture_session_id', $sessionId)
            ->where('token_type', 'otp')
            ->where('expires_at', '>=', now())
            ->where('is_used', false)
            ->first();

        if (! $submissionGrant) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        if ($submittedSubmissionToken !== null
            && ! hash_equals($submissionGrant->token_value, $submittedSubmissionToken)) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        $qrToken = AttendanceToken::whereKey($qrTokenId)
            ->where('lecture_session_id', $sessionId)
            ->where('token_type', 'qr')
            ->where('expires_at', '>=', now())
            ->where('is_used', false)
            ->first();

        if (! $qrToken) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        if (! hash_equals($qrTokenHash, hash('sha256', $qrToken->token_value))) {
            $this->invalidateVerificationContext($request);

            return null;
        }

        return [
            'submission_token' => $submissionGrant->token_value,
            'submission_grant' => $submissionGrant,
            'qr_token' => $qrToken,
        ];
    }

    private function consumeVerificationContext(Request $request, AttendanceToken $submissionGrant): void
    {
        $submissionGrant->update([
            'is_used' => true,
            'used_at' => now(),
        ]);

        $request->session()->forget(self::VERIFICATION_SESSION_KEY);
    }

    private function attendanceViewData(
        LectureSession $session,
        int $remainingSeconds,
        ?string $submissionToken,
        bool $attendanceCompleted = false,
        ?string $successMessage = null
    ): array {
        return [
            'sessionId' => $session->id,
            'sessionDetails' => $session,
            'remainingSeconds' => $attendanceCompleted ? 0 : $remainingSeconds,
            'submissionToken' => $submissionToken,
            'attendanceCompleted' => $attendanceCompleted,
            'successMessage' => $successMessage,
        ];
    }

    private function buildSubmissionResponse(
        Request $request,
        bool $success,
        string $message,
        int $status = 200,
        array $payload = []
    ) {
        $responsePayload = array_merge([
            'success' => $success,
            'message' => $message,
        ], $payload);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($responsePayload, $status);
        }

        if ($success && isset($payload['session']) && $payload['session'] instanceof LectureSession) {
            return response()->view('student.attendance', $this->attendanceViewData(
                session: $payload['session'],
                remainingSeconds: 0,
                submissionToken: null,
                attendanceCompleted: true,
                successMessage: $message,
            ));
        }

        return back()
            ->withInput($request->except(['otp', 'submission_token']))
            ->with('error', $message);
    }

    private function resolveDeviceFingerprint(Request $request): ?string
    {
        return $request->header('X-Device-Fingerprint')
            ?? $request->userAgent()
            ?? null;
    }

    private function handleAttendanceSubmission(Request $request, int $sessionId)
    {
        $request->validate([
            'student_number' => 'required|string|max:50',
            'otp' => 'required|string|size:6',
            'submission_token' => 'required|string|size:64',
        ]);

        $session = LectureSession::with(['subject', 'lecturer', 'hall'])->find($sessionId);

        if (! $session) {
            return $this->buildSubmissionResponse($request, false, __('session.not_found'), 404);
        }

        $verification = $this->resolveVerificationContext(
            $request,
            $sessionId,
            (string) $request->input('submission_token')
        );

        if (! $verification) {
            return $this->buildSubmissionResponse($request, false, __('session.token_expired'), 403);
        }

        $this->finalizeExpiredSession($session);

        if ($session->qr_expired || $session->status !== 'active') {
            $this->invalidateVerificationContext($request);

            return $this->buildSubmissionResponse($request, false, __('session.token_expired'), 403);
        }

        $remainingSeconds = $this->calculateRemainingSeconds($session);

        if ($remainingSeconds <= 0) {
            $this->invalidateVerificationContext($request);

            return $this->buildSubmissionResponse($request, false, __('session.token_expired'), 403);
        }

        if ((string) $session->session_otp !== (string) $request->input('otp')) {
            return $this->buildSubmissionResponse($request, false, __('student.invalid_otp'), 422);
        }

        $student = Student::where('student_number', $request->input('student_number'))->first();

        if (! $student) {
            return $this->buildSubmissionResponse($request, false, __('student.not_found'), 422);
        }

        if ($session->subject_id) {
            $isEnrolled = \DB::table('enrollments')
                ->where('student_id', $student->id)
                ->where('subject_id', $session->subject_id)
                ->exists();

            if (! $isEnrolled) {
                return $this->buildSubmissionResponse($request, false, __('student.not_enrolled_in_subject'), 422);
            }
        }

        $existingAttendance = Attendance::where('student_id', $student->id)
            ->where('lecture_session_id', $sessionId)
            ->first();

        if ($existingAttendance) {
            return $this->buildSubmissionResponse($request, false, __('student.already_attended'), 409);
        }

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'lecture_session_id' => $sessionId,
            'attendance_token_id' => $verification['submission_grant']->id,
            'attendance_time' => now(),
            'attendance_method' => 'qr_scan',
            'attendance_status' => 'present',
            'ip_address' => $request->ip(),
            'device_fingerprint' => $this->resolveDeviceFingerprint($request),
        ]);

        $this->consumeVerificationContext($request, $verification['submission_grant']);

        return $this->buildSubmissionResponse(
            $request,
            true,
            __('student.attendance_recorded'),
            200,
            [
                'attendance_time' => $attendance->attendance_time->toIso8601String(),
                'session' => $session,
            ]
        );
    }

    /**
     * Generate and display QR code for a session.
     */
    public function showQr(LectureSession $session)
    {
        abort_unless($session->canManageQr(auth()->user()), 403);

        $session->refresh();

        if ($session->qr_expires_at && now()->greaterThan($session->qr_expires_at)) {
            $session->update([
                'qr_expired' => true,
                'status' => 'completed',
                'actual_end' => now(),
            ]);
            $session->refresh();
        }

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

        AttendanceToken::where('lecture_session_id', $session->id)
            ->where('expires_at', '<', now())
            ->delete();

        $existingToken = AttendanceToken::where('lecture_session_id', $session->id)
            ->where('expires_at', '>=', now())
            ->where('is_used', false)
            ->first();

        if (! $existingToken) {
            $tokenValue = Str::random(32);
            $expiresAt = now()->addSeconds($session->qr_refresh_rate);

            $existingToken = AttendanceToken::create([
                'lecture_session_id' => $session->id,
                'token_type' => 'qr',
                'token_value' => $tokenValue,
                'expires_at' => $expiresAt,
            ]);

            $otp = random_int(100000, 999999);
            $session->update([
                'session_otp' => $otp,
                'qr_started_at' => now(),
                'qr_expires_at' => $expiresAt,
            ]);
        } else {
            $tokenValue = $existingToken->token_value;
            $otp = $session->session_otp;

            if (empty($otp)) {
                $otp = random_int(100000, 999999);
                $session->update(['session_otp' => $otp]);
            }

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
     * Legacy scan endpoint is intentionally blocked.
     */
    public function scan(Request $request, LectureSession $session)
    {
        abort(404);
    }

    /**
     * Verify token from QR code.
     */
    public function verifyToken(Request $request, string $tokenValue)
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
            $this->invalidateVerificationContext($request);
            abort(403, __('session.token_expired'));
        }

        $sessionDetails = LectureSession::with(['subject', 'lecturer', 'hall'])
            ->findOrFail($session->id);

        $submissionToken = $this->createVerificationContext($request, $sessionDetails, $token, $remainingSeconds);

        return view('student.attendance', $this->attendanceViewData(
            session: $sessionDetails,
            remainingSeconds: $remainingSeconds,
            submissionToken: $submissionToken,
        ));
    }

    /**
     * Legacy session route is allowed only after a verified QR flow has already
     * established a session-bound submission grant.
     */
    public function verifySession(Request $request, int $sessionId)
    {
        $verification = $this->resolveVerificationContext($request, $sessionId);

        if (! $verification) {
            abort(403, __('session.token_expired'));
        }

        $session = LectureSession::with(['subject', 'lecturer', 'hall'])->findOrFail($sessionId);

        $this->finalizeExpiredSession($session);

        if ($session->qr_expired || $session->status !== 'active') {
            $this->invalidateVerificationContext($request);
            abort(403, __('session.qr_session_expired'));
        }

        $remainingSeconds = $this->calculateRemainingSeconds($session);

        if ($remainingSeconds <= 0) {
            $this->invalidateVerificationContext($request);
            abort(403, __('session.token_expired'));
        }

        return view('student.attendance', $this->attendanceViewData(
            session: $session,
            remainingSeconds: $remainingSeconds,
            submissionToken: $verification['submission_token'],
        ));
    }

    /**
     * Store attendance using the same secure flow as the live sync endpoint.
     */
    public function store(Request $request, int $sessionId)
    {
        return $this->handleAttendanceSubmission($request, $sessionId);
    }

    /**
     * Check attendance status - guarded by the same verified QR flow.
     */
    public function checkStatus(Request $request, int $sessionId): JsonResponse
    {
        if (! $this->resolveVerificationContext($request, $sessionId)) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => __('session.token_expired'),
                'remaining_seconds' => 0,
            ], 403);
        }

        $request->validate([
            'student_number' => 'required|string',
        ]);

        $studentNumber = $request->input('student_number');
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
            $this->invalidateVerificationContext($request);

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => __('session.token_expired'),
                'remaining_seconds' => 0,
            ], 403);
        }

        $student = Student::where('student_number', $studentNumber)->first();

        if ($student) {
            $attendance = Attendance::where('student_id', $student->id)
                ->where('lecture_session_id', $sessionId)
                ->first();

            if ($attendance) {
                return response()->json([
                    'success' => true,
                    'status' => 'recorded',
                    'message' => __('student.attendance_recorded'),
                    'attendance_time' => $attendance->attendance_time->toIso8601String(),
                    'remaining_seconds' => $remainingSeconds,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'status' => 'processing',
            'message' => __('student.checking'),
            'remaining_seconds' => $remainingSeconds,
        ]);
    }

    /**
     * The live student page submits here.
     */
    public function storeSync(Request $request, int $sessionId)
    {
        return $this->handleAttendanceSubmission($request, $sessionId);
    }
}
