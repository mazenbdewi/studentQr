<?php

namespace App\Providers;

use App\Http\Responses\Filament\LoginResponse;
use App\Models\ScheduleImportIssue;
use App\Models\ScheduleImportRow;
use App\Models\SubjectSectionScheduleSlot;
use App\Policies\ActivityPolicy;
use App\Policies\ScheduleImportIssuePolicy;
use App\Policies\ScheduleImportRowPolicy;
use App\Policies\SubjectSectionScheduleSlotPolicy;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AppServiceProvider extends ServiceProvider
{
    protected array $policies = [
        Activity::class => ActivityPolicy::class,
        ScheduleImportRow::class => ScheduleImportRowPolicy::class,
        ScheduleImportIssue::class => ScheduleImportIssuePolicy::class,
        SubjectSectionScheduleSlot::class => SubjectSectionScheduleSlotPolicy::class,
    ];

    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        Livewire::component('username_personal_info', \App\Livewire\Filament\Profile\UsernamePersonalInfo::class);
        Livewire::component('update_password', \App\Livewire\Filament\Profile\UpdatePassword::class);
        Livewire::component('update_pin_code', \App\Livewire\Filament\Profile\UpdatePinCode::class);

        $this->configurePolicies();

        $this->configureDB();

        $this->configureModels();

        $this->configureFilament();

        $this->configureLimit();
    }

    private function configurePolicies(): void
    {
        Gate::before(fn ($user): ?bool => $user->isSuperAdmin() ? true : null);
        Gate::define('manageAcademicTerms', fn ($user): bool => $user->isAdmin());

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ([
            ScheduleImportRowPolicy::PREVIEW_BLOCKED_WEEKLY_SLOT_RECONCILIATION,
            ScheduleImportRowPolicy::RECONCILE_BLOCKED_WEEKLY_SLOTS,
            ScheduleImportRowPolicy::CREATE_LECTURER_IDENTITY_FROM_SOURCE,
            ScheduleImportRowPolicy::CHANGE_RECONCILED_LECTURER,
            ScheduleImportRowPolicy::CHANGE_RECONCILED_HALL,
            ScheduleImportRowPolicy::CHANGE_RECONCILED_WEEKLY_TIME,
            ScheduleImportRowPolicy::EXCLUDE_WEEKLY_SLOT_FROM_CURRENT_BATCH,
            ScheduleImportRowPolicy::VIEW_RECONCILIATION_AUDIT_HISTORY,
            ScheduleImportRowPolicy::EXPORT_BLOCKED_WEEKLY_SLOT_REPORTS,
            ScheduleImportRowPolicy::MANAGE_HALL_METADATA,
            ScheduleImportRowPolicy::EXPORT_HALL_METADATA,
            ScheduleImportRowPolicy::IMPORT_HALL_METADATA,
            ScheduleImportRowPolicy::PREVIEW_HALL_METADATA_IMPORT,
            ScheduleImportRowPolicy::PREVIEW_GROUPED_HALL_ASSIGNMENT,
            ScheduleImportRowPolicy::CONFIRM_GROUPED_HALL_ASSIGNMENT_WITH_WARNING,
        ] as $ability) {
            Gate::define($ability, fn ($user): bool => $user->hasRole('admin')
                || (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($ability)));
        }

        Gate::define(
            ScheduleImportRowPolicy::OVERRIDE_LECTURE_SESSION_TEACHING_PERIOD,
            function ($user): bool {
                try {
                    return method_exists($user, 'hasPermissionTo')
                        && $user->hasPermissionTo(ScheduleImportRowPolicy::OVERRIDE_LECTURE_SESSION_TEACHING_PERIOD);
                } catch (PermissionDoesNotExist) {
                    return false;
                }
            },
        );
    }

    private function configureDB(): void
    {
        DB::prohibitDestructiveCommands($this->app->environment('production'));
    }

    private function configureModels(): void
    {
        Model::preventAccessingMissingAttributes();

        Model::unguard();
    }

    private function configureFilament(): void
    {
        FilamentShield::prohibitDestructiveCommands($this->app->isProduction());

        Column::configureUsing(fn (Column $column) => $column->toggleable());

        Table::configureUsing(fn (Table $table) => $table
            ->reorderableColumns()
            ->deferColumnManager(false)
            ->deferFilters(false)
            ->paginationPageOptions([10, 25, 50])
        );
    }

    private function configureLimit(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
