<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchProgressController extends Controller
{
    public function __invoke(Request $request, ImportBatch $batch): JsonResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['super-admin', 'admin'])
                && ($batch->created_by === null || $batch->created_by === $request->user()?->id),
            403,
        );

        return response()->json($batch->fresh()->progressPayload());
    }
}
