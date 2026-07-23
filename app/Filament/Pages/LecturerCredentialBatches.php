<?php

namespace App\Filament\Pages;

use App\Models\LecturerCredentialBatch;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class LecturerCredentialBatches extends Page
{
    protected static ?string $slug = 'lecturer-credential-batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected string $view = 'filament.pages.lecturer-credential-batches';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user?->hasRole('super-admin') || $user?->can('view lecturer credential batches');
    }

    public static function getNavigationLabel(): string
    {
        return 'دفعات بيانات دخول المحاضرين';
    }

    public function batches()
    {
        return LecturerCredentialBatch::query()->with(['academicTerm', 'generatedBy'])->latest('generated_at')->get();
    }
}
