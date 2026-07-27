<?php

namespace App\Filament\Pages;

use App\Exports\LecturerAccountReportExport;
use App\Models\AcademicTerm;
use App\Models\Lecturer;
use App\Models\LecturerAccountGenerationItem;
use App\Models\LecturerAccountGenerationRun;
use App\Models\User;
use App\Services\LecturerAccountPreparationService;
use App\Services\LecturerBulkPasswordResetService;
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
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        return __('filament-dashboard.navigation.initial_setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.navigation.account_preparation');
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
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
                TextColumn::make('user.login_username')
                    ->label('اسم المستخدم')
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
                $this->downloadLatestSuccessReportAction(),
                $this->downloadLatestErrorReportAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    $this->resetLecturerPasswordsBulkAction(),
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

    private function resetLecturerPasswordsBulkAction(): BulkAction
    {
        return BulkAction::make('reset-lecturer-passwords')
            ->label('إعادة ضبط كلمات مرور المحاضرين')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('إعادة ضبط كلمات مرور المحاضرين')
            ->modalDescription('سيتم إنشاء كلمات مرور مؤقتة جديدة للحسابات المحددة، وستتوقف كلمات المرور الحالية عن العمل فور نجاح العملية. سيُنشأ ملف مشفر قابل للتنزيل، وسيُطلب من كل محاضر تغيير كلمة المرور عند أول تسجيل دخول.')
            ->visible(fn (): bool => (bool) (Filament::auth()->user()?->hasRole('super-admin') || Filament::auth()->user()?->can('reset lecturer passwords')))
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
                    ->nullable(),
                Forms\Components\Checkbox::make('confirmed_password_reset')
                    ->label('أؤكد أن كلمات المرور الحالية ستتوقف عن العمل فور نجاح العملية.')
                    ->accepted()
                    ->required(),
                Forms\Components\Placeholder::make('reset_password_warning')
                    ->label('تنبيه أمني')
                    ->content('لا يتم عرض كلمات المرور المؤقتة في المتصفح. سيظهر الملف المشفر في صفحة دفعات بيانات الدخول بعد النجاح.')
                    ->columnSpanFull(),
            ])
            ->action(function (EloquentCollection $records, array $data): void {
                $term = filled($data['academic_term_id'] ?? null)
                    ? AcademicTerm::query()->findOrFail($data['academic_term_id'])
                    : null;
                $service = app(LecturerBulkPasswordResetService::class);
                $preview = $service->preview($records->pluck('user_id')->all(), $term);
                $service->execute($preview, $preview['fingerprint'], Filament::auth()->user(), $term);

                Notification::make()
                    ->title('تمت إعادة ضبط كلمات مرور المحاضرين بأمان')
                    ->body('تم إنشاء ملف مشفر قابل للتنزيل من صفحة دفعات بيانات دخول المحاضرين.')
                    ->success()
                    ->send();
            });
    }

    private function downloadLatestSuccessReportAction(): Action
    {
        return Action::make('download-latest-lecturer-account-success-report')
            ->label(__('lecturer-account-preparation.actions.successful_operations_report'))
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action(fn (): ?BinaryFileResponse => $this->downloadLatestAccountReport('success'));
    }

    private function downloadLatestErrorReportAction(): Action
    {
        return Action::make('download-latest-lecturer-account-error-report')
            ->label(__('lecturer-account-preparation.actions.error_report'))
            ->icon('heroicon-o-exclamation-triangle')
            ->color('gray')
            ->action(fn (): ?BinaryFileResponse => $this->downloadLatestAccountReport('error'));
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

    private function downloadLatestAccountReport(string $type): ?BinaryFileResponse
    {
        $run = LecturerAccountGenerationRun::query()
            ->latest('completed_at')
            ->latest('id')
            ->first();

        if (! $run instanceof LecturerAccountGenerationRun) {
            Notification::make()
                ->title(__('lecture-session.not_available'))
                ->warning()
                ->send();

            return null;
        }

        $items = $run->items()->with(['lecturer', 'user.roles'])->orderBy('id')->get();
        $successResults = [
            LecturerAccountGenerationItem::RESULT_ACCOUNT_CREATED,
            LecturerAccountGenerationItem::RESULT_EXISTING_ACCOUNT,
            LecturerAccountGenerationItem::RESULT_ROLE_ADDED,
            LecturerAccountGenerationItem::RESULT_USERNAME_ASSIGNED,
            LecturerAccountGenerationItem::RESULT_TEMPORARY_PASSWORD_RESET,
        ];
        $rows = $type === 'success'
            ? $items->whereIn('result', $successResults)->map(fn (LecturerAccountGenerationItem $item): array => [
                'اسم المدرس' => $item->lecturer?->name,
                'اسم الدخول' => $item->login_username,
                'النتيجة' => $item->result,
                'الحساب المنشأ أو المعاد استخدامه' => $item->user_id ? __('lecturer-account-preparation.results.'.$item->result) : '',
                'الدور' => $item->user?->hasRole('course_lecturer') ? 'course_lecturer' : '',
                'الملاحظة' => $item->message,
            ])->values()->all()
            : $items->reject(fn (LecturerAccountGenerationItem $item): bool => in_array($item->result, $successResults, true))
                ->map(fn (LecturerAccountGenerationItem $item): array => [
                    'اسم المدرس' => $item->lecturer?->name,
                    'اسم الدخول المقترح' => $item->login_username,
                    'رمز الخطأ' => $item->error_code,
                    'السبب بالعربية' => $item->message,
                    'الإجراء المقترح' => __('lecturer-account-preparation.report_actions.'.($item->error_code ?: 'default')) !== 'lecturer-account-preparation.report_actions.'.($item->error_code ?: 'default')
                        ? __('lecturer-account-preparation.report_actions.'.($item->error_code ?: 'default'))
                        : __('lecturer-account-preparation.report_actions.default'),
                ])->values()->all();

        return Excel::download(
            $type === 'success' ? LecturerAccountReportExport::success($rows) : LecturerAccountReportExport::errors($rows),
            $type === 'success' ? 'lecturer-account-success-report.xlsx' : 'lecturer-account-errors-report.xlsx',
            ExcelWriter::XLSX,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
