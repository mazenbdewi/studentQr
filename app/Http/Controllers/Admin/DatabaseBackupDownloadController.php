<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupDownloadController extends Controller
{
    public function __invoke(Request $request, string $fileName, DatabaseBackupService $backups): BinaryFileResponse
    {
        abort_unless($backups->canManageBackups($request->user()), 403);

        $backup = $backups->findByFileName($fileName);

        return response()->download($backup['path'], $backup['file_name'], [
            'Content-Type' => str_ends_with($backup['file_name'], '.zip') ? 'application/zip' : 'application/sql',
        ]);
    }
}
