<?php

namespace App\Providers\Filament;

use Andreia\FilamentNordTheme\FilamentNordThemePlugin;
use App\Filament\Pages\AcademicTermArchive;
use App\Filament\Pages\AcademicTermManagement;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\DatabaseBackups;
use App\Filament\Pages\LecturerAccountPreparation;
use App\Filament\Pages\LecturerCredentialBatches;
use App\Filament\Pages\ManaraEnrollmentImport;
use App\Filament\Pages\ManaraScheduleImport;
use App\Filament\Pages\PortalSettings;
use App\Filament\Pages\ScheduleImportReconciliationIndex;
use App\Filament\Pages\ScheduleImportReconciliationReport;
use App\Filament\Pages\UserGuide;
use App\Filament\Pages\WeeklySchedule;
use App\Filament\Pages\WeeklyScheduleReports;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Faculties\FacultyResource;
use App\Filament\Resources\FailedAttempts\FailedAttemptResource;
use App\Filament\Resources\Halls\HallResource;
use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Filament\Resources\Seminars\SeminarResource;
use App\Filament\Resources\StudentDevices\StudentDeviceResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\EnsurePasswordChangeIsNotRequired;
use App\Http\Middleware\EnsurePinIsVerified;
use App\Http\Middleware\SetAdminLocale;
use App\Livewire\Filament\Profile\UpdatePassword;
use App\Livewire\Filament\Profile\UpdatePinCode;
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
            ->login(Login::class)
            ->colors([
                'primary' => Color::hex('#1E40AF'),
            ])
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/favicon.ico'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Only show specific resources - hide: Attendances, FailedAttempts, StudentDevices
            ->resources([
                LectureSessionResource::class,
                SeminarResource::class,
                StudentResource::class,
                SubjectResource::class,
                FacultyResource::class,
                DepartmentResource::class,
                HallResource::class,
                UserResource::class,
                AuditLogResource::class,
                // Hidden resources (can be accessed programmatically if needed):
                //  AttendanceResource::class,
                // FailedAttemptResource::class,
                // StudentDeviceResource::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Dashboard::class,
                AcademicTermManagement::class,
                AcademicTermArchive::class,
                ManaraEnrollmentImport::class,
                ManaraScheduleImport::class,
                WeeklySchedule::class,
                WeeklyScheduleReports::class,
                LecturerAccountPreparation::class,
                LecturerCredentialBatches::class,
                ScheduleImportReconciliationIndex::class,
                ScheduleImportReconciliationReport::class,
                PortalSettings::class,
                UserGuide::class,
                DatabaseBackups::class,
            ])
            ->widgets([
                \App\Filament\Widgets\TodaysLecturesWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),

                //                FilamentNordThemePlugin::make(),
                //                FilamentBackgroundsPlugin::make(),
                BreezyCore::make()
                    ->myProfile()
                    ->myProfileComponents([
                        'update_password' => UpdatePassword::class,
                        'update_pin_code' => UpdatePinCode::class,
                    ]),
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
                EnsurePasswordChangeIsNotRequired::class,
                EnsurePinIsVerified::class,
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
