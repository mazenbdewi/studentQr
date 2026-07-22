<?php

namespace App\Filament\Pages;

use App\Models\Lecturer;
use App\Models\User;
use App\Services\LecturerAccountPreparationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class LecturerAccountPreparation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'lecturer-account-preparation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected string $view = 'filament.pages.lecturer-account-preparation';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasAnyRole(['super-admin', 'admin']);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('weekly-schedule.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('lecturer-account-preparation.title');
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public function getTitle(): string
    {
        return __('lecturer-account-preparation.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Lecturer::query()->with(['user.roles'])->withCount('scheduleSlots'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('lecturer-account-preparation.fields.lecturer_name'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('linked_account')
                    ->label(__('lecturer-account-preparation.fields.linked_account'))
                    ->state(function (Lecturer $record): string {
                        $user = $this->linkedUser($record);

                        return $user instanceof User
                            ? $user->name
                            : __('lecturer-account-preparation.statuses.missing_account');
                    })
                    ->wrap(),
                TextColumn::make('user.email')
                    ->label(__('lecturer-account-preparation.fields.email'))
                    ->placeholder(__('lecture-session.not_available'))
                    ->searchable(),
                TextColumn::make('account_status')
                    ->label(__('lecturer-account-preparation.fields.account_status'))
                    ->state(fn (Lecturer $record): string => $this->accountStatusLabel($record))
                    ->badge(),
                TextColumn::make('course_lecturer_role_status')
                    ->label(__('lecturer-account-preparation.fields.course_lecturer_role_status'))
                    ->state(fn (Lecturer $record): string => $this->linkedUser($record)?->hasRole('course_lecturer')
                        ? __('lecturer-account-preparation.statuses.role_granted')
                        : __('lecturer-account-preparation.statuses.role_missing'))
                    ->badge(),
                TextColumn::make('schedule_slots_count')
                    ->label(__('lecturer-account-preparation.fields.weekly_slots_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('readiness_status')
                    ->label(__('lecturer-account-preparation.fields.readiness_status'))
                    ->state(fn (Lecturer $record): string => $this->readinessLabel($record))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('readiness')
                    ->label(__('lecturer-account-preparation.fields.readiness_status'))
                    ->options(__('lecturer-account-preparation.readiness_filter'))
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'missing_account' => $query->whereNull('user_id'),
                            'linked' => $query->whereNotNull('user_id'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                $this->createAccountAction(),
                $this->linkExistingAccountAction(),
                $this->grantCourseLecturerRoleAction(),
            ])
            ->defaultSort('name');
    }

    private function createAccountAction(): Action
    {
        return Action::make('create-login-account')
            ->label(__('lecturer-account-preparation.actions.create_account'))
            ->visible(fn (Lecturer $record): bool => blank($record->user_id))
            ->form(fn (Lecturer $record): array => [
                Forms\Components\TextInput::make('name')
                    ->label(__('lecturer-account-preparation.fields.lecturer_name'))
                    ->default($record->name)
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('email')
                    ->label(__('lecturer-account-preparation.fields.email'))
                    ->email()
                    ->required()
                    ->rule(Rule::unique('users', 'email'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label(__('lecturer-account-preparation.fields.password'))
                    ->password()
                    ->autocomplete('new-password')
                    ->required()
                    ->confirmed(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label(__('lecturer-account-preparation.fields.password_confirmation'))
                    ->password()
                    ->autocomplete('new-password')
                    ->required(),
            ])
            ->action(function (Lecturer $record, array $data): void {
                app(LecturerAccountPreparationService::class)->createLoginAccount(
                    $record,
                    (string) $data['email'],
                    (string) $data['password'],
                    (string) $data['password_confirmation'],
                );

                $this->successNotification();
            });
    }

    private function linkExistingAccountAction(): Action
    {
        return Action::make('link-existing-account')
            ->label(__('lecturer-account-preparation.actions.link_existing_account'))
            ->visible(fn (Lecturer $record): bool => blank($record->user_id))
            ->form([
                Forms\Components\Select::make('user_id')
                    ->label(__('lecturer-account-preparation.fields.linked_account'))
                    ->options(fn (): array => User::query()
                        ->withoutTrashed()
                        ->whereNotIn('id', Lecturer::query()->whereNotNull('user_id')->pluck('user_id'))
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} — {$user->email}"])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
            ])
            ->action(function (Lecturer $record, array $data): void {
                $user = User::query()->findOrFail($data['user_id']);

                app(LecturerAccountPreparationService::class)->linkExistingAccount($record, $user);

                $this->successNotification();
            });
    }

    private function grantCourseLecturerRoleAction(): Action
    {
        return Action::make('grant-course-lecturer-role')
            ->label(__('lecturer-account-preparation.actions.grant_course_lecturer_role'))
            ->visible(fn (Lecturer $record): bool => filled($record->user_id) && ! ($this->linkedUser($record)?->hasRole('course_lecturer') ?? false))
            ->requiresConfirmation()
            ->action(function (Lecturer $record): void {
                app(LecturerAccountPreparationService::class)->grantCourseLecturerRole($record);

                $this->successNotification();
            });
    }

    private function accountStatusLabel(Lecturer $record): string
    {
        $user = $this->linkedUser($record);

        if (! $record->user_id) {
            return __('lecturer-account-preparation.statuses.missing_account');
        }

        if (! $user instanceof User) {
            return __('lecturer-account-preparation.statuses.broken_link');
        }

        if ($user->trashed()) {
            return __('lecturer-account-preparation.statuses.deleted_account');
        }

        if (! ($user->is_active ?? true) || $user->status !== 'active') {
            return __('lecturer-account-preparation.statuses.inactive_account');
        }

        return __('lecturer-account-preparation.statuses.active_account');
    }

    private function readinessLabel(Lecturer $record): string
    {
        return __('lecturer-account-preparation.statuses.'.app(LecturerAccountPreparationService::class)->readinessStatus($record));
    }

    private function linkedUser(Lecturer $record): ?User
    {
        $user = $record->user;

        return $user instanceof User ? $user : null;
    }

    private function successNotification(): void
    {
        Notification::make()
            ->title(__('lecturer-account-preparation.saved'))
            ->success()
            ->send();
    }
}
