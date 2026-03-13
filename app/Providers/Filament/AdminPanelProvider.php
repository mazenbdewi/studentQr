<?php

namespace App\Providers\Filament;

use Andreia\FilamentNordTheme\FilamentNordThemePlugin;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\FailedAttempts\FailedAttemptResource;
use App\Filament\Resources\Halls\HallResource;
use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\StudentDevices\StudentDeviceResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\SetAdminLocale;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::hex('#1E40AF'),
            ])
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/favicon.ico'))
            // Only show specific resources - hide: Attendances, FailedAttempts, StudentDevices
            ->resources([
                config('filament-logger.activity_resource'),
                StudentResource::class,
                SubjectResource::class,
                LectureSessionResource::class,
                HallResource::class,
                DepartmentResource::class,
                UserResource::class,
                // Hidden resources (can be accessed programmatically if needed):
                // AttendanceResource::class,
                // FailedAttemptResource::class,
                // StudentDeviceResource::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                StatsOverviewWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                //                FilamentNordThemePlugin::make(),
                //                FilamentBackgroundsPlugin::make(),
                BreezyCore::make()
                    ->myProfile(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetAdminLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                Action::make('ar')
                    ->label('العربية')
                    ->url(fn () => route('lang.switch', 'ar')),

                Action::make('en')
                    ->label('English')
                    ->url(fn () => route('lang.switch', 'en')),

            ]);
    }
}
