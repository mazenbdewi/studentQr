<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceToken;
use App\Models\LectureSession;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Support\QrUrlGenerator;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class AttendanceController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'attendance_verification';
    private const COMPLETED_ATTENDANCE_SESSION_KEY = 'attendance_completed';
    private const SUBMISSION_TOKEN_VERSION = 1;

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
        $session->syncLifecycleState(refresh: false);
    }

    private function storeCompletedAttendanceContext(
        Request $request,
        LectureSession $session,
        Student $student,
        Attendance $attendance
    ): void {
        if (! $request->hasSession()) {
            return;
        }

        $context = $request->session()->get(self::COMPLETED_ATTENDANCE_SESSION_KEY, []);

        if (! is_array($context)) {
            $context = [];
        }

        $context[(string) $session->id] = [
            'attendance_id' => $attendance->id,
            'student_id' => $student->id,
            'student_number' => $student->student_number,
        ];

        $request->session()->put(self::COMPLETED_ATTENDANCE_SESSION_KEY, $context);
    }

    private function forgetCompletedAttendanceContext(Request $request, int $sessionId): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $context = $request->session()->get(self::COMPLETED_ATTENDANCE_SESSION_KEY, []);

        if (! is_array($context)) {
            $request->session()->forget(self::COMPLETED_ATTENDANCE_SESSION_KEY);

            return;
        }

        unset($context[(string) $sessionId]);

        if ($context === []) {
            $request->session()->forget(self::COMPLETED_ATTENDANCE_SESSION_KEY);

            return;
        }

        $request->session()->put(self::COMPLETED_ATTENDANCE_SESSION_KEY, $context);
    }

    private function resolveCompletedAttendance(Request $request, int $sessionId): ?Attendance
    {
        if (! $request->hasSession()) {
            return null;
        }

        $context = $request->session()->get(self::COMPLETED_ATTENDANCE_SESSION_KEY, []);

        if (! is_array($context)) {
            return null;
        }

        $sessionContext = $context[(string) $sessionId] ?? null;

        if (! is_array($sessionContext)) {
            return null;
        }

        $attendanceId = (int) ($sessionContext['attendance_id'] ?? 0);
        $studentId = (int) ($sessionContext['student_id'] ?? 0);

        if ($attendanceId <= 0 || $studentId <= 0) {
            $this->forgetCompletedAttendanceContext($request, $sessionId);

            return null;
        }

        $attendance = Attendance::with('student')
            ->whereKey($attendanceId)
            ->where('lecture_session_id', $sessionId)
            ->where('student_id', $studentId)
            ->first();

        if (! $attendance) {
            $this->forgetCompletedAttendanceContext($request, $sessionId);

            return null;
        }

        return $attendance;
    }

    private function formatAttendanceTime(mixed $attendanceTime): ?string
    {
        if ($attendanceTime instanceof Carbon) {
            return $attendanceTime->toIso8601String();
        }

        if ($attendanceTime instanceof \DateTimeInterface) {
            return Carbon::instance($attendanceTime)->toIso8601String();
        }

        if (! is_string($attendanceTime) || trim($attendanceTime) === '') {
            return null;
        }

        return Carbon::parse($attendanceTime)->toIso8601String();
    }

    private function findExistingAttendance(int $sessionId, int $studentId): ?Attendance
    {
        return Attendance::with('student')
            ->where('lecture_session_id', $sessionId)
            ->where('student_id', $studentId)
            ->first();
    }

    private function renderCompletedAttendanceView(
        Request $request,
        LectureSession $session,
        Attendance $attendance,
        ?string $message = null
    ) {
        $this->invalidateVerificationContext($request);

        $sessionDetails = LectureSession::with(['subject', 'lecturer', 'hall'])
            ->findOrFail($session->id);

        return view('student.attendance', $this->attendanceViewData(
            session: $sessionDetails,
            remainingSeconds: 0,
            submissionToken: null,
            attendanceCompleted: true,
            successMessage: $message ?? __('student.attendance_already_submitted'),
            studentNumber: $attendance->student?->student_number,
            attendanceTime: $this->formatAttendanceTime($attendance->attendance_time),
        ));
    }

    private function invalidateVerificationContext(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

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
        $expiresAt = now()->addSeconds(max(1, $remainingSeconds))->getTimestamp();

        return Crypt::encryptString(json_encode([
            'v' => self::SUBMISSION_TOKEN_VERSION,
            'session_id' => $session->id,
            'qr_token_id' => $qrToken->id,
            'qr_token_hash' => hash('sha256', $qrToken->token_value),
            'expires_at' => $expiresAt,
            'nonce' => Str::random(16),
        ], JSON_THROW_ON_ERROR));
    }

    private function resolveVerificationContext(
        Request $request,
        int $sessionId,
        ?string $submittedSubmissionToken = null
    ): ?array {
        if (! is_string($submittedSubmissionToken) || $submittedSubmissionToken === '') {
            return null;
        }

        try {
            $context = json_decode(
                Crypt::decryptString($submittedSubmissionToken),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($context) || (int) ($context['session_id'] ?? 0) !== $sessionId) {
            return null;
        }

        if ((int) ($context['v'] ?? 0) !== self::SUBMISSION_TOKEN_VERSION) {
            return null;
        }

        if ((int) ($context['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $qrTokenId = (int) ($context['qr_token_id'] ?? 0);
        $qrTokenHash = $context['qr_token_hash'] ?? null;

        if ($qrTokenId <= 0 || ! is_string($qrTokenHash) || $qrTokenHash === '') {
            return null;
        }

        return [
            'submission_token' => $submittedSubmissionToken,
            'submission_grant' => null,
            'qr_token_id' => $qrTokenId,
        ];
    }

    private function consumeVerificationContext(Request $request, ?AttendanceToken $submissionGrant): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::VERIFICATION_SESSION_KEY);
        }
    }

    private function attendanceViewData(
        LectureSession $session,
        int $remainingSeconds,
        ?string $submissionToken,
        bool $attendanceCompleted = false,
        ?string $successMessage = null,
        ?string $studentNumber = null,
        ?string $attendanceTime = null
    ): array {
        return [
            'sessionId' => $session->id,
            'sessionDetails' => $session,
            'remainingSeconds' => $attendanceCompleted ? 0 : $remainingSeconds,
            'submissionToken' => $submissionToken,
            'attendanceCompleted' => $attendanceCompleted,
            'successMessage' => $successMessage,
            'studentNumberValue' => $studentNumber,
            'attendanceTime' => $attendanceTime,
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
            unset($responsePayload['session']);

            return response()->json($responsePayload, $status);
        }

        if (! $request->hasSession()) {
            unset($responsePayload['session']);

            return response()->json($responsePayload, $status);
        }

        if ($success && isset($payload['session']) && $payload['session'] instanceof LectureSession) {
            return response()->view('student.attendance', $this->attendanceViewData(
                session: $payload['session'],
                remainingSeconds: 0,
                submissionToken: null,
                attendanceCompleted: true,
                successMessage: $message,
                studentNumber: $payload['student_number'] ?? null,
                attendanceTime: $payload['attendance_time'] ?? null,
            ));
        }

        return back()
            ->withInput($request->except(['otp', 'submission_token']))
            ->with('error', $message);
    }

    private function logFailedAttendanceAttempt(Request $request, int $sessionId, string $reason, ?int $studentId = null): void
    {
        app(ActivityLogger::class)->logAttendance('failed_attendance_attempt', [
            'lecture_session_id' => $sessionId,
            'student_id' => $studentId,
            'status' => 'failed',
            'reason' => $reason,
        ]);
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
            'submission_token' => 'required|string|max:2048',
        ]);

        $session = LectureSession::query()
            ->select([
                'id',
                'subject_id',
                'session_date',
                'end_time',
                'actual_end',
                'status',
                'session_otp',
                'qr_expired',
                'qr_started_at',
                'qr_expires_at',
                'qr_refresh_rate',
            ])
            ->find($sessionId);

        if (! $session) {
            $this->logFailedAttendanceAttempt($request, $sessionId, 'session_not_found');
            return $this->buildSubmissionResponse($request, false, __('session.not_found'), 404);
        }

        if ($completedAttendance = $this->resolveCompletedAttendance($request, $sessionId)) {
            return $this->renderCompletedAttendanceView($request, $session, $completedAttendance);
        }

        $verification = $this->resolveVerificationContext(
            $request,
            $sessionId,
            (string) $request->input('submission_token')
        );

        if (! $verification) {
            $this->logFailedAttendanceAttempt($request, $sessionId, 'verification_expired');
            return $this->buildSubmissionResponse($request, false, __('session.token_expired'), 403);
        }

        $this->finalizeExpiredSession($session);

        if ($session->qr_expired || $session->status !== 'active') {
            $this->invalidateVerificationContext($request);
            $this->logFailedAttendanceAttempt($request, $sessionId, 'session_inactive');

            return $this->buildSubmissionResponse($request, false, __('session.token_expired'), 403);
        }

        $remainingSeconds = $this->calculateRemainingSeconds($session);

        if ($remainingSeconds <= 0) {
            $this->invalidateVerificationContext($request);
            $this->logFailedAttendanceAttempt($request, $sessionId, 'session_expired');

            return $this->buildSubmissionResponse($request, false, __('session.token_expired'), 403);
        }

        if ((string) $session->session_otp !== (string) $request->input('otp')) {
            $this->logFailedAttendanceAttempt($request, $sessionId, 'invalid_otp');
            return $this->buildSubmissionResponse($request, false, __('student.invalid_otp'), 422);
        }

        $studentNumber = (string) $request->input('student_number');

        $studentQuery = Student::query()
            ->select(['students.id', 'students.student_number'])
            ->where('students.student_number', $studentNumber);

        if ($session->subject_id) {
            $studentQuery
                ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
                ->where('enrollments.subject_id', $session->subject_id);
        }

        $student = $studentQuery->first();

        if (! $student) {
            if ($session->subject_id) {
                $unenrolledStudent = Student::query()
                    ->select(['id'])
                    ->where('student_number', $studentNumber)
                    ->first();

                if ($unenrolledStudent) {
                    $this->logFailedAttendanceAttempt($request, $sessionId, 'student_not_enrolled', $unenrolledStudent->id);

                    return $this->buildSubmissionResponse($request, false, __('student.not_enrolled_in_subject'), 422);
                }
            }

            $this->logFailedAttendanceAttempt($request, $sessionId, 'student_not_found');
            return $this->buildSubmissionResponse($request, false, __('student.not_found'), 422);
        }

        $attendanceTime = now();
        $inserted = DB::table('attendances')->insertOrIgnore([
            'student_id' => $student->id,
            'lecture_session_id' => $sessionId,
            'attendance_token_id' => $verification['qr_token_id'],
            'attendance_time' => $attendanceTime,
            'attendance_method' => 'qr_scan',
            'attendance_status' => 'present',
            'ip_address' => $request->ip(),
            'device_fingerprint' => $this->resolveDeviceFingerprint($request),
            'created_at' => $attendanceTime,
            'updated_at' => $attendanceTime,
        ]);

        if (! $inserted) {
            $existingAttendance = Attendance::query()
                ->select(['id', 'student_id', 'lecture_session_id', 'attendance_time'])
                ->where('lecture_session_id', $sessionId)
                ->where('student_id', $student->id)
                ->first();

            if ($existingAttendance) {
                $this->storeCompletedAttendanceContext($request, $session, $student, $existingAttendance);
            }

            return $this->buildSubmissionResponse(
                $request,
                true,
                __('student.attendance_already_submitted'),
                200,
                [
                    'attendance_time' => $this->formatAttendanceTime($existingAttendance?->attendance_time),
                    'session' => $session,
                    'student_number' => $student->student_number,
                ]
            );
        }

        $this->consumeVerificationContext($request, $verification['submission_grant']);

        if (config('activity-log.log_successful_attendance', false)) {
            app(ActivityLogger::class)->logAttendance('attendance_registered', [
                'student_id' => $student->id,
                'lecture_session_id' => $sessionId,
                'status' => 'present',
            ]);
        }

        return $this->buildSubmissionResponse(
            $request,
            true,
            __('student.attendance_recorded'),
            200,
            [
                'attendance_time' => $this->formatAttendanceTime($attendanceTime),
                'session' => $session,
                'student_number' => $student->student_number,
            ]
        );
    }

    /**
     * Generate and display QR code for a session.
     */
    public function showQr(LectureSession $session, QrUrlGenerator $qrUrlGenerator)
    {
        abort_unless($session->canManageQr(auth()->user()), 403);

        $session->syncLifecycleState();

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
            ->where('token_type', 'qr')
            ->where('expires_at', '>=', now())
            ->where('is_used', false)
            ->first();

        $generatedNewToken = false;

        if (! $existingToken) {
            $generatedNewToken = true;
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

        $tokenData = $qrUrlGenerator->attendanceVerificationUrl($tokenValue);

        $writer = new PngWriter;
        $qrCode = new \Endroid\QrCode\QrCode($tokenData);
        $result = $writer->write($qrCode);
        $qr = $result->getDataUri();

        app(ActivityLogger::class)->log([
            'category' => 'lecture_sessions',
            'action' => $generatedNewToken ? 'qr_regenerated' : 'qr_shown',
            'model_type' => $session::class,
            'model_id' => $session->id,
            'description' => 'lecture_session_qr_viewed',
            'context' => [
                'lecture_session_id' => $session->id,
            ],
        ]);

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
        $token = AttendanceToken::with(['lectureSession.subject', 'lectureSession.lecturer', 'lectureSession.hall'])
            ->where('token_value', $tokenValue)
            ->where('token_type', 'qr')
            ->first();

        if (! $token) {
            abort(403, __('session.token_expired'));
        }

        $session = $token->lectureSession;

        if (! $session) {
            abort(403, __('session.not_found'));
        }

        $this->finalizeExpiredSession($session);

        if ($completedAttendance = $this->resolveCompletedAttendance($request, $session->id)) {
            return $this->renderCompletedAttendanceView($request, $session, $completedAttendance);
        }

        if ($token->expires_at < now() || $token->is_used) {
            abort(403, __('session.token_expired'));
        }

        if ($session->qr_expired || $session->status !== 'active') {
            abort(403, __('session.qr_session_expired'));
        }

        $remainingSeconds = $this->calculateRemainingSeconds($session);

        if ($remainingSeconds <= 0) {
            $this->invalidateVerificationContext($request);
            abort(403, __('session.token_expired'));
        }

        $submissionToken = $this->createVerificationContext($request, $session, $token, $remainingSeconds);

        return view('student.attendance-fast', $this->attendanceViewData(
            session: $session,
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
        $session = LectureSession::with(['subject', 'lecturer', 'hall'])->findOrFail($sessionId);

        if ($completedAttendance = $this->resolveCompletedAttendance($request, $sessionId)) {
            return $this->renderCompletedAttendanceView($request, $session, $completedAttendance);
        }

        $verification = $this->resolveVerificationContext(
            $request,
            $sessionId,
            (string) $request->input('submission_token')
        );

        if (! $verification) {
            abort(403, __('session.token_expired'));
        }

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
        if (! $this->resolveVerificationContext(
            $request,
            $sessionId,
            (string) $request->input('submission_token')
        )) {
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
