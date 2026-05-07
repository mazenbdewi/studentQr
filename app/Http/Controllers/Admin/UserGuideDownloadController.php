<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserGuidePdfService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserGuideDownloadController extends Controller
{
    public function __invoke(Request $request, UserGuidePdfService $userGuidePdf): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user && $user->canAccessPanel(Filament::getPanel('admin')),
            403,
            __('auth.access_denied_page')
        );

        return $userGuidePdf->download($user);
    }
}
