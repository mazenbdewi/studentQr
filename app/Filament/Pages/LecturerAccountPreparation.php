<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\Lecturer;
use App\Models\User;
use App\Services\LecturerAccountPreparationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->headerActions([
                $this->previewBulkPreparationAction(),
                $this->createBulkAccountsAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->resetTemporaryPasswordsBulkAction(),
                ]),
            ])
            ->recordActions([])
            ->defaultSort('name');
    }

    private function previewBulkPreparationAction(): Action
    {
        return Action::make('preview-bulk-lecturer-account-preparation')
            ->label(__('lecturer-account-preparation.actions.preview_bulk_preparation'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(__('lecturer-account-preparation.actions.preview_bulk_preparation'))
            ->modalSubmitAction(false)
            ->form([
                Forms\Components\Select::make('academic_term_id')
                    ->label(__('lecture-session.academic_term'))
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderByDesc('id')
                        ->pluck('display_name', 'id')
                        ->all())
                    ->default(fn (): ?int => AcademicTerm::query()->latest('id')->value('id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->required(),
                Forms\Components\Placeholder::make('bulk_account_preparation_preview')
                    ->label(__('lecturer-account-preparation.actions.preview_bulk_preparation'))
                    ->content(fn (Get $get): string => $this->bulkPreparationPreviewText($get))
                    ->columnSpanFull(),
            ]);
    }

    private function createBulkAccountsAction(): Action
    {
        return Action::make('create-bulk-lecturer-accounts')
            ->label(__('lecturer-account-preparation.actions.create_bulk_accounts'))
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('lecturer-account-preparation.actions.create_bulk_accounts'))
            ->modalSubmitActionLabel(__('lecturer-account-preparation.actions.create_bulk_accounts'))
            ->form([
                Forms\Components\Select::make('academic_term_id')
                    ->label(__('lecture-session.academic_term'))
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderByDesc('id')
                        ->pluck('display_name', 'id')
                        ->all())
                    ->default(fn (): ?int => AcademicTerm::query()->latest('id')->value('id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->required(),
                Forms\Components\Placeholder::make('bulk_account_preparation_preview')
                    ->label(__('lecturer-account-preparation.actions.preview_bulk_preparation'))
                    ->content(fn (Get $get): string => $this->bulkPreparationPreviewText($get))
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('one_time_download_warning')
                    ->label(__('lecturer-account-preparation.one_time_download_title'))
                    ->content(__('lecturer-account-preparation.one_time_download_warning'))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): ?StreamedResponse {
                $term = AcademicTerm::query()->findOrFail($data['academic_term_id']);
                $result = app(LecturerAccountPreparationService::class)->prepareBulkAccounts(
                    $term,
                    Filament::auth()->user(),
                );

                Notification::make()
                    ->title(__('lecturer-account-preparation.bulk_completed_title'))
                    ->body(__('lecturer-account-preparation.bulk_completed_body', [
                        'created' => $result['created_account_count'],
                        'roles' => $result['granted_role_count'],
                        'blocked' => $result['blocked_count'],
                    ]))
                    ->success()
                    ->send();

                if ($result['credential_rows'] === []) {
                    return null;
                }

                return $this->credentialsCsvResponse(
                    $result['credential_rows'],
                    'lecturer-temporary-credentials-'.$result['generation_run_id'].'.csv',
                );
            });
    }

    private function resetTemporaryPasswordsBulkAction(): BulkAction
    {
        return BulkAction::make('reset-bulk-lecturer-temporary-passwords')
            ->label(__('lecturer-account-preparation.actions.reset_temporary_passwords'))
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('lecturer-account-preparation.actions.reset_temporary_passwords'))
            ->form([
                Forms\Components\Select::make('academic_term_id')
                    ->label(__('lecture-session.academic_term'))
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderByDesc('id')
                        ->pluck('display_name', 'id')
                        ->all())
                    ->default(fn (): ?int => AcademicTerm::query()->latest('id')->value('id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Forms\Components\Placeholder::make('one_time_download_warning')
                    ->label(__('lecturer-account-preparation.one_time_download_title'))
                    ->content(__('lecturer-account-preparation.one_time_download_warning'))
                    ->columnSpanFull(),
            ])
            ->action(function (EloquentCollection $records, array $data): ?StreamedResponse {
                $term = AcademicTerm::query()->findOrFail($data['academic_term_id']);
                /** @var array<int, Lecturer> $lecturers */
                $lecturers = $records
                    ->filter(fn (mixed $record): bool => $record instanceof Lecturer)
                    ->values()
                    ->all();
                $result = app(LecturerAccountPreparationService::class)->resetTemporaryPasswords(
                    $term,
                    $lecturers,
                    Filament::auth()->user(),
                );

                if ($result['credential_rows'] === []) {
                    Notification::make()
                        ->title(__('lecturer-account-preparation.no_credentials_generated'))
                        ->warning()
                        ->send();

                    return null;
                }

                return $this->credentialsCsvResponse(
                    $result['credential_rows'],
                    'lecturer-temporary-password-reset-'.$result['generation_run_id'].'.csv',
                );
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

    private function bulkPreparationPreviewText(Get $get): string
    {
        $termId = $get('academic_term_id');

        if (blank($termId)) {
            return __('lecturer-account-preparation.bulk_preview_empty');
        }

        $term = AcademicTerm::query()->find($termId);

        if (! $term) {
            return __('lecturer-account-preparation.bulk_preview_empty');
        }

        $preview = app(LecturerAccountPreparationService::class)->previewBulkPreparation($term);

        return __('lecturer-account-preparation.bulk_preview_summary', [
            'total' => $preview['referenced_lecturer_count'],
            'ready' => $preview['already_ready_count'],
            'create' => $preview['accounts_to_create_count'],
            'roles' => $preview['roles_to_grant_count'],
            'blocked' => $preview['blocked_count'],
        ]);
    }

    private function credentialsCsvResponse(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'اسم المدرس بالعربية',
                'اسم الدخول',
                'كلمة المرور المؤقتة',
                'حالة الحساب',
                'يجب تغيير كلمة المرور عند أول دخول',
            ], ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['lecturer_name'],
                    $row['login_username'],
                    $row['temporary_password'],
                    $row['account_status'],
                    $row['must_change_password'] ? 'نعم' : 'لا',
                ], ',', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
