<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceToken;
use App\Models\LectureSession;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    private function ensureUserCanManageSession(LectureSession $session): void
    {
        abort_unless($session->canManageQr(auth()->user()), 403);
    }

    /**
     * Check if session is still active - AJAX endpoint.
     */
    public function sessionStatus(LectureSession $session): JsonResponse
    {
        $this->ensureUserCanManageSession($session);

        $session->syncLifecycleState();

        return response()->json([
            'active' => $session->status === 'active' && $session->isWithinQrAvailabilityWindow(),
        ]);
    }

    /**
     * Mark the current QR code as expired without ending the lecture session.
     */
    public function expireQr(LectureSession $session): JsonResponse
    {
        $this->ensureUserCanManageSession($session);

        $session->syncLifecycleState();

        if ($session->status === 'completed' || $session->status === 'cancelled' || $session->hasReachedScheduledEnd()) {
            return response()->json([
                'success' => true,
                'message' => 'QR code has already been expired',
            ]);
        }

        AttendanceToken::query()
            ->where('lecture_session_id', $session->id)
            ->where('token_type', 'qr')
            ->where('expires_at', '>', now())
            ->where('is_used', false)
            ->update(['expires_at' => now()]);

        $session->update([
            'qr_expired' => true,
            'qr_expires_at' => now(),
        ]);

        app(ActivityLogger::class)->log([
            'category' => 'lecture_sessions',
            'action' => 'qr_expired',
            'model_type' => $session::class,
            'model_id' => $session->id,
            'description' => 'lecture_session_qr_expired',
            'context' => [
                'lecture_session_id' => $session->id,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR code has been expired',
        ]);
    }
}
