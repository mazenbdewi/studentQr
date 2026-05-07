<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class UserGuide extends Page
{
    protected static ?string $slug = 'user-guide';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    protected string $view = 'filament.pages.user-guide';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        return (bool) ($user && $panel && $user->canAccessPanel($panel));
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.user_guide');
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public function getTitle(): string
    {
        return __('user-guide.page.title');
    }

    public function getSubheading(): ?string
    {
        return __('user-guide.page.description');
    }
}
