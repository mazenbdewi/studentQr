<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LectureSession;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    /**
     * Check if session is still active - AJAX endpoint.
     */
    public function sessionStatus(LectureSession $session): JsonResponse
    {
        // Refresh to get latest data from database
        $session->refresh();

        // Server-side enforcement: Check if QR has expired based on qr_expires_at timestamp
        if ($session->qr_expires_at && now()->greaterThan($session->qr_expires_at)) {
            // Only update if not already expired
            if (! $session->qr_expired) {
                $session->update([
                    'qr_expired' => true,
                    'status' => 'completed',
                    'actual_end' => now(),
                ]);
                $session->refresh();
            }
        }

        return response()->json([
            'active' => $session->status === 'active' && ! $session->qr_expired,
        ]);
    }

    /**
     * Mark QR code as expired for a session and end the lecture session.
     */
    public function expireQr(LectureSession $session): JsonResponse
    {
        // Refresh to ensure we're working with latest data
        $session->refresh();

        // If already expired, return success to avoid duplicate processing
        if ($session->qr_expired) {
            return response()->json([
                'success' => true,
                'message' => 'QR code has already been expired',
            ]);
        }

        // Mark QR as expired, set status to completed, and set actual_end
        $session->update([
            'qr_expired' => true,
            'status' => 'completed',
            'actual_end' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR code has been expired and session has been ended',
        ]);
    }
}
