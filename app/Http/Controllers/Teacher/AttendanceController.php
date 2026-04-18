<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LectureSession;
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
            'active' => $session->status === 'active' && ! $session->qr_expired,
        ]);
    }

    /**
     * Mark QR code as expired for a session and end the lecture session.
     */
    public function expireQr(LectureSession $session): JsonResponse
    {
        $this->ensureUserCanManageSession($session);

        $session->syncLifecycleState();

        // If already expired, return success to avoid duplicate processing
        if ($session->qr_expired || $session->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'QR code has already been expired',
            ]);
        }

        // Mark QR as expired, set status to completed, and set actual_end
        $session->update([
            'qr_expired' => true,
            'status' => 'completed',
            'actual_end' => $session->actual_end ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR code has been expired and session has been ended',
        ]);
    }
}
