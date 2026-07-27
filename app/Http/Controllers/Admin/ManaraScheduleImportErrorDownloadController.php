<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManaraScheduleImportErrorDownloadController extends Controller
{
    public function __invoke(Request $request, string $fileName): StreamedResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'admin']), 403);

        $fileName = basename($fileName);
        $path = "import-errors/{$fileName}";

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download(
            $path,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
