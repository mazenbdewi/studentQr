<?php

namespace App\Filament\Resources\LectureSessions;

use App\Filament\Resources\LectureSessions\RelationManagers\AbsentStudentsRelationManager;
use App\Filament\Resources\LectureSessions\RelationManagers\AttendancesRelationManager;
use App\Models\AcademicTerm;
use App\Models\AppSetting;
use App\Models\Hall;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\User;
use App\Policies\ScheduleImportRowPolicy;
use App\Services\ActivityLogger;
use App\Services\LectureSessionLecturerResolver;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LectureSessionResource extends Resource
{
    protected static ?string $model = LectureSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    public static function getModelLabel(): string
    {
        return __('lecture-session.singular');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.daily_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-dashboard.lecture_sessions');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getPluralModelLabel(): string
    {
        return __('lecture-session.plural');
    }

    public static function getCreatePageTitle(): string
    {
        return __('lecture-session.create_title');
    }

    public static function getCreateActionLabel(): string
    {
        return __('lecture-session.create');
    }

    public static function getRecordTitle($record): ?string
    {
        return __('lecture-session.record_title').' #'.$record->id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('academic_term_id')
                    ->label(__('lecture-session.academic_term'))
                    ->options(fn (): array => AcademicTerm::query()
                        ->orderByDesc('id')
                        ->pluck('display_name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('subject_section_id', null);
                        $set('lecturer_id', null);
                    }),

                Forms\Components\Select::make('subject_id')
                    ->label(__('lecture-session.subject'))
                    ->relationship(
                        name: 'subject',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => static::scopeSubjectQueryForCurrentUser($query),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rule(static::subjectBelongsToCurrentLecturerRule())
                    ->validationMessages([
                        'exists' => __('lecture-session.subject_not_assigned_to_lecturer'),
                    ])
                    ->live()
                    ->afterStateUpdated(function (callable $set, Get $get, $state) {
                        $set('lecturer_id', static::resolveLecturerIdForSubjectAndSection(
                            $state,
                            null,
                            $get('academic_term_id'),
                        ));
                        $set('subject_section_id', null);
                    }),

                Forms\Components\Select::make('subject_section_id')
                    ->label(__('subjects.section_code'))
                    ->options(fn (Get $get): array => static::getSectionOptionsForSubject(
                        $get('subject_id'),
                        $get('academic_term_id'),
                    ))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->disabled(fn (Get $get): bool => blank($get('subject_id')))
                    ->required(fn (Get $get): bool => static::subjectHasSections(
                        $get('subject_id'),
                        $get('academic_term_id'),
                    ))
                    ->placeholder(__('subjects.select_subject_first'))
                    ->afterStateUpdated(function (callable $set, Get $get, mixed $state): void {
                        $set('lecturer_id', static::resolveLecturerIdForSubjectAndSection(
                            $get('subject_id'),
                            $state,
                            $get('academic_term_id'),
                        ));
                    })
                    ->live()
                    ->helperText(__('lecture-session.section_helper_text')),

                Forms\Components\Select::make('hall_id')
                    ->label(__('lecture-session.hall'))
                    ->relationship(
                        name: 'hall',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->whereNull('deleted_at')
                            ->where('is_active', true),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\DatePicker::make('session_date')
                    ->label(__('lecture-session.session_date'))
                    ->native(false)
                    ->required(),

                Forms\Components\TimePicker::make('start_time')
                    ->label(__('lecture-session.start_time'))
                    ->seconds(false)
                    ->native(false)
                    ->required(),

                Forms\Components\TimePicker::make('end_time')
                    ->label(__('lecture-session.end_time'))
                    ->seconds(false)
                    ->native(false)
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label(__('lecture-session.status'))
                    ->options([
                        'scheduled' => __('lecture-session.status_scheduled'),
                        'active' => __('lecture-session.status_active'),
                        'completed' => __('lecture-session.status_completed'),
                        'cancelled' => __('lecture-session.status_cancelled'),
                    ])
                    ->default('scheduled')
                    ->required(),

                Forms\Components\Select::make('attendance_mode')
                    ->label(__('lecture-session.attendance_mode'))
                    ->options([
                        'qr_only' => __('lecture-session.mode_qr_only'),
                        'qr_otp' => __('lecture-session.mode_qr_otp'),
                        'manual' => __('lecture-session.mode_manual'),
                    ])
                    ->default('qr_otp')
                    ->required()
                    ->disabled(),

                Forms\Components\TextInput::make('qr_refresh_rate')
                    ->label(__('lecture-session.qr_refresh_rate'))
                    ->numeric()
                    ->minValue(AppSetting::MIN_QR_REFRESH_RATE)
                    ->default(fn (): int => AppSetting::defaultQrRefreshRate())
                    ->suffix(__('lecture-session.seconds')),

                Forms\Components\Textarea::make('notes')
                    ->label(__('lecture-session.notes'))
                    ->nullable()
                    ->columnSpanFull(),

                Forms\Components\Select::make('lecturer_id')
                    ->label(__('lecture-session.lecturer'))
                    ->options(fn (Get $get): array => static::manualLecturerOptions(
                        $get('academic_term_id'),
                        $get('subject_id'),
                        $get('subject_section_id'),
                    ))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder(__('lecture-session.subject_has_no_lecturer'))
                    ->required()
                    ->validationMessages([
                        'required' => __('lecture-session.subject_has_no_lecturer'),
                    ])
                    ->disabled(fn (): bool => auth()->user()?->hasRole('course_lecturer') === true)
                    ->dehydrated(),

                Forms\Components\Textarea::make('teaching_period_override_reason')
                    ->label(__('lecture-session.teaching_period_override_reason'))
                    ->helperText(__('lecture-session.teaching_period_override_help'))
                    ->visible(fn (): bool => Gate::allows(ScheduleImportRowPolicy::OVERRIDE_LECTURE_SESSION_TEACHING_PERIOD))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('missing_lecturer_warning')
                    ->label(__('lecture-session.missing_lecturer_warning_title'))
                    ->content(fn (Get $get): string => static::manualLecturerWarning(
                        $get('academic_term_id'),
                        $get('subject_id'),
                        $get('subject_section_id'),
                    ))
                    ->visible(fn (Get $get): bool => static::shouldShowMissingLecturerWarning(
                        $get('subject_id'),
                        $get('subject_section_id'),
                        $get('academic_term_id'),
                    ))
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm font-medium text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('1800s')
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')
                    ->label(__('lecture-session.subject'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject.code')
                    ->label(__('subjects.code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('subject.subject_type')
                    ->label(__('subjects.subject_type'))
                    ->formatStateUsing(function (LectureSession $record): string {
                        $section = $record->subjectSection;

                        if ($section instanceof SubjectSection) {
                            return $section->section_type_label;
                        }

                        $subject = $record->subject;

                        if ($subject instanceof Subject) {
                            return $subject->subject_type_label;
                        }

                        return __('subjects.not_available');
                    })
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subjectSection.code')
                    ->label(__('subjects.section_code'))
                    ->badge()
                    ->placeholder(__('subjects.not_available'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('lecturer.name')
                    ->label('المدرس')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('hall.name')
                    ->label(__('lecture-session.hall'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('session_date')
                    ->label(__('lecture-session.session_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label(__('lecture-session.start_time'))
                    ->time(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label(__('lecture-session.end_time'))
                    ->time(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('lecture-session.status'))
                    ->colors([
                        'warning' => 'scheduled',
                        'success' => 'active',
                        'secondary' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => __("lecture-session.status_{$state}")),

                Tables\Columns\TextColumn::make('actual_attendance')
                    ->label(__('lecture-session.actual_attendance'))
                    ->getStateUsing(fn (LectureSession $record): int => (int) ($record->actual_attendance_count ?? 0)),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('lecture-session.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject')
                    ->label(__('lecture-session.subject'))
                    ->relationship(
                        name: 'subject',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => static::scopeSubjectQueryForCurrentUser($query),
                    ),
                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\SelectFilter::make('lecturer')
                    ->label('المدرس')
                    ->relationship('lecturer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('lecture-session.status'))
                    ->options([
                        'scheduled' => __('lecture-session.status_scheduled'),
                        'active' => __('lecture-session.status_active'),
                        'completed' => __('lecture-session.status_completed'),
                        'cancelled' => __('lecture-session.status_cancelled'),
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('start')
                    ->label(__('lecture-session.start_session'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (LectureSession $record) {
                        $original = $record->getOriginal();
                        $record->syncLifecycleState();

                        if ($record->status !== 'scheduled') {
                            return;
                        }

                        $otp = random_int(100000, 999999);

                        $record->update([
                            'status' => 'active',
                            'actual_start' => now(),
                            'actual_end' => null,
                            'session_otp' => $otp,
                            'qr_expired' => false,
                            'qr_started_at' => null,
                            'qr_expires_at' => null,
                        ]);

                        app(ActivityLogger::class)->logModelUpdated(
                            $record->fresh(),
                            $original,
                            'lecture_sessions',
                            'lecture_session_started'
                        );
                    })
                    ->visible(false),

                \Filament\Actions\Action::make('end')
                    ->label(__('lecture-session.end_session'))
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->action(function (LectureSession $record) {
                        $original = $record->getOriginal();
                        $record->syncLifecycleState();

                        if ($record->status !== 'active') {
                            return;
                        }

                        $record->update([
                            'status' => 'completed',
                            'actual_end' => now(),
                            'qr_expired' => true,
                        ]);

                        app(ActivityLogger::class)->log([
                            'category' => 'lecture_sessions',
                            'action' => 'qr_expired',
                            'model_type' => $record::class,
                            'model_id' => $record->id,
                            'description' => 'lecture_session_ended',
                            'old_values' => [
                                'status' => $original['status'] ?? null,
                                'qr_expired' => $original['qr_expired'] ?? null,
                            ],
                            'new_values' => [
                                'status' => 'completed',
                                'qr_expired' => true,
                            ],
                            'context' => [
                                'lecture_session_id' => $record->id,
                            ],
                        ]);
                    })
                    ->visible(fn (LectureSession $record) => ! $record->trashed() && $record->status === 'active' && ! $record->hasReachedScheduledEnd()),

                ActionsAction::make('view_qr')
                    ->label(__('lecture-session.view_qr'))
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (LectureSession $record) => route('teacher.lecture-session.qr', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (LectureSession $record) => ! $record->trashed() && $record->shouldShowQrAction(auth()->user())),

                ActionsAction::make('session_ended')
                    ->label(__('lecture-session.session_ended'))
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->disabled()
                    ->visible(fn (LectureSession $record) => ! $record->trashed() && ($record->status === 'completed' || $record->status === 'cancelled' || $record->hasReachedScheduledEnd())),

                ActionsAction::make('view_attendance')
                    ->label(__('attendance.view_attendance'))
                    ->color('success')
                    ->icon('heroicon-o-users')
                    ->url(fn (LectureSession $record) => LectureSessionResource::getUrl('view', [
                        'record' => $record,
                        'activeRelationManager' => 'attendances',
                    ]))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->visible(fn (LectureSession $record): bool => static::canCurrentUserEditLectureSession($record)),
                DeleteAction::make()
                    ->visible(fn (LectureSession $record): bool => static::canCurrentUserDeleteLectureSession($record)),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
            ])->defaultSort('session_date', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('course_lecturer') !== true
                            || AppSetting::lecturerCanDeleteLectureSessions()),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AttendancesRelationManager::class,
            AbsentStudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLectureSessions::route('/'),
            'create' => Pages\CreateLectureSession::route('/create'),
            'edit' => Pages\EditLectureSession::route('/{record}/edit'),
            'view' => Pages\ViewLectureSession::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        LectureSession::syncExpiredSessions();

        $query = LectureSession::query()
            ->forCurrentAcademicTerm()
            ->with(['subject', 'subjectSection', 'lecturer', 'hall'])
            ->withCount([
                'attendances as actual_attendance_count' => fn (Builder $query) => $query
                    ->select(DB::raw('count(distinct student_id)')),
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (auth()->user()->hasRole('course_lecturer')) {
            return $query->where('lecturer_id', auth()->id());
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'admin', 'manager', 'course_lecturer']);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->hasRole('super-admin')
                || $user->can('create manual lecture sessions')
            );
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record instanceof LectureSession
            && parent::canEdit($record)
            && static::canCurrentUserEditLectureSession($record);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record instanceof LectureSession
            && parent::canDelete($record)
            && static::canCurrentUserDeleteLectureSession($record);
    }

    public static function canCurrentUserEditLectureSession(LectureSession $record): bool
    {
        if ($record->trashed()) {
            return false;
        }

        $user = auth()->user();

        if ($user?->hasRole('course_lecturer')) {
            return (int) $record->lecturer_id === (int) $user->id
                && AppSetting::lecturerCanEditLectureSessions();
        }

        return true;
    }

    public static function canCurrentUserDeleteLectureSession(LectureSession $record): bool
    {
        if ($record->trashed()) {
            return false;
        }

        $user = auth()->user();

        if ($user?->hasRole('course_lecturer')) {
            return (int) $record->lecturer_id === (int) $user->id
                && AppSetting::lecturerCanDeleteLectureSessions();
        }

        return true;
    }

    public static function scopeSubjectQueryForCurrentUser(Builder $query): Builder
    {
        $query->whereNull('deleted_at');

        $user = auth()->user();

        if ($user?->hasRole('course_lecturer')) {
            $query->whereHas('sections', fn (Builder $sectionsQuery) => $sectionsQuery->where('lecturer_id', $user->id));
        }

        return $query;
    }

    public static function subjectBelongsToCurrentLecturerRule(): mixed
    {
        $user = auth()->user();

        return Rule::exists('subjects', 'id')
            ->where(function ($query) use ($user): void {
                $query->whereNull('deleted_at');

                if ($user?->hasRole('course_lecturer')) {
                    $query->where(function ($query) use ($user): void {
                        $query->whereExists(function ($sectionsQuery) use ($user): void {
                            $sectionsQuery
                                ->selectRaw('1')
                                ->from('subject_sections')
                                ->whereColumn('subject_sections.subject_id', 'subjects.id')
                                ->where('subject_sections.lecturer_id', $user->id);
                        });
                    });
                }
            });
    }

    public static function ensureSubjectCanBeUsedByCurrentUser(array $data): array
    {
        $subjectId = $data['subject_id'] ?? null;

        if (blank($subjectId)) {
            return $data;
        }

        $subject = Subject::query()
            ->withoutTrashed()
            ->find($subjectId);

        if (! $subject) {
            return $data;
        }

        $user = auth()->user();

        $section = null;

        if (filled($data['subject_section_id'] ?? null)) {
            $resolvedSection = $subject->sections()
                ->whereKey($data['subject_section_id'])
                ->first();

            if (! $resolvedSection instanceof SubjectSection) {
                throw ValidationException::withMessages([
                    'subject_section_id' => __('subjects.section_must_belong_to_subject'),
                ]);
            }

            $section = $resolvedSection;
        }

        if ($user?->hasRole('course_lecturer') && ! self::subjectCanBeUsedByLecturer(
            $subject,
            $user->id,
            $section,
            $data['academic_term_id'] ?? null,
        )) {
            throw ValidationException::withMessages([
                'subject_id' => __('lecture-session.subject_not_assigned_to_lecturer'),
            ]);
        }

        $lecturerOptions = static::manualLecturerOptions(
            $data['academic_term_id'] ?? null,
            $subject->id,
            $section?->id,
        );

        if ($lecturerOptions === []) {
            throw ValidationException::withMessages([
                'lecturer_id' => __('lecture-session.subject_has_no_lecturer'),
            ]);
        }

        $selectedLecturerId = (int) ($data['lecturer_id'] ?? 0);

        if ($selectedLecturerId === 0) {
            $selectedLecturerId = count($lecturerOptions) === 1 ? (int) array_key_first($lecturerOptions) : 0;
        }

        if ($selectedLecturerId === 0 || ! array_key_exists($selectedLecturerId, $lecturerOptions)) {
            throw ValidationException::withMessages([
                'lecturer_id' => __('lecture-session.lecturer_must_match_selected_section_schedule'),
            ]);
        }

        $data['lecturer_id'] = $selectedLecturerId;

        static::validateManualSessionData($data);

        return $data;
    }

    public static function getSectionOptionsForSubject(int|string|null $subjectId, int|string|null $academicTermId = null): array
    {
        if (blank($subjectId)) {
            return [];
        }

        $query = SubjectSection::query()
            ->where('subject_id', $subjectId)
            ->when(filled($academicTermId), fn (Builder $query): Builder => $query->where('academic_term_id', $academicTermId))
            ->orderBy('code');

        $user = auth()->user();

        if ($user?->hasRole('course_lecturer')) {
            $query->where('lecturer_id', $user->id);
        }

        return $query
            ->pluck('code', 'id')
            ->all();
    }

    public static function subjectHasSections(int|string|null $subjectId, int|string|null $academicTermId = null): bool
    {
        return filled($subjectId)
            && \App\Models\SubjectSection::query()
                ->where('subject_id', $subjectId)
                ->when(filled($academicTermId), fn (Builder $query): Builder => $query->where('academic_term_id', $academicTermId))
                ->exists();
    }

    public static function resolveLecturerIdForSubjectAndSection(
        int|string|null $subjectId,
        int|string|null $sectionId,
        int|string|null $academicTermId = null,
    ): ?int {
        return app(LectureSessionLecturerResolver::class)->defaultUserId($academicTermId, $subjectId, $sectionId);
    }

    public static function shouldShowMissingLecturerWarning(
        int|string|null $subjectId,
        int|string|null $sectionId,
        int|string|null $academicTermId = null,
    ): bool {
        if (blank($subjectId)) {
            return false;
        }

        if (static::subjectHasSections($subjectId, $academicTermId) && blank($sectionId)) {
            return false;
        }

        return static::manualLecturerOptions($academicTermId, $subjectId, $sectionId) === [];
    }

    public static function manualLecturerWarning(int|string|null $academicTermId, int|string|null $subjectId, int|string|null $sectionId): string
    {
        $resolution = app(LectureSessionLecturerResolver::class)->resolve($academicTermId, $subjectId, $sectionId);
        $problem = $resolution['problems'][0]['code'] ?? null;

        return match ($problem) {
            'inactive_account' => __('lecture-session.section_lecturer_account_inactive'),
            'missing_course_lecturer_role' => __('lecture-session.section_lecturer_missing_role'),
            'missing_linked_user' => __('lecture-session.section_lecturer_missing_account'),
            default => __('lecture-session.missing_lecturer_warning'),
        };
    }

    private static function subjectCanBeUsedByLecturer(
        Subject $subject,
        int $lecturerId,
        ?SubjectSection $section = null,
        int|string|null $academicTermId = null,
    ): bool {
        return app(LectureSessionLecturerResolver::class)->userCanUseSubject(
            $lecturerId,
            $subject->id,
            $academicTermId,
            $section?->id,
        );
    }

    /** @return array<int, string> */
    public static function manualLecturerOptions(
        int|string|null $academicTermId = null,
        int|string|null $subjectId = null,
        int|string|null $sectionId = null,
    ): array {
        if (filled($subjectId)) {
            $options = app(LectureSessionLecturerResolver::class)->options($academicTermId, $subjectId, $sectionId);

            if (auth()->user()?->hasRole('course_lecturer')) {
                return array_intersect_key($options, [(int) auth()->id() => true]);
            }

            return $options;
        }

        $query = User::query()
            ->withoutTrashed()
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'course_lecturer'))
            ->orderBy('name');

        return $query->pluck('name', 'id')->all();
    }

    /** @param array<string, mixed> $data */
    public static function prepareManualSessionData(array $data): array
    {
        $data['subject_section_schedule_slot_id'] = null;
        $data['lecture_session_generation_run_id'] = null;
        $data['generated_from_weekly_schedule_at'] = null;
        unset($data['teaching_period_override_reason']);

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    public static function teachingPeriodOverrideAuditContext(array $data): array
    {
        $reason = trim((string) ($data['teaching_period_override_reason'] ?? ''));

        return $reason === '' ? [] : ['teaching_period_override_reason' => $reason];
    }

    /** @param array<string, mixed> $data */
    public static function validateManualSessionData(array $data): void
    {
        $errors = [];
        $term = filled($data['academic_term_id'] ?? null)
            ? AcademicTerm::query()->find($data['academic_term_id'])
            : null;

        if (! $term instanceof AcademicTerm) {
            $errors['academic_term_id'] = __('validation.required', ['attribute' => __('lecture-session.academic_term')]);
        }

        if (filled($data['subject_section_id'] ?? null)) {
            $section = SubjectSection::query()->find($data['subject_section_id']);

            if (! $section
                || (int) $section->subject_id !== (int) ($data['subject_id'] ?? 0)
                || (int) $section->academic_term_id !== (int) ($data['academic_term_id'] ?? 0)) {
                $errors['subject_section_id'] = __('lecture-session.section_must_belong_to_subject_and_term');
            }
        }

        if (filled($data['lecturer_id'] ?? null)) {
            $lecturer = User::query()
                ->withoutTrashed()
                ->whereKey($data['lecturer_id'])
                ->where('status', 'active')
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'course_lecturer'))
                ->first();

            if (! $lecturer instanceof User) {
                $errors['lecturer_id'] = __('lecture-session.lecturer_must_be_active_course_lecturer');
            }
        }

        if (filled($data['hall_id'] ?? null)) {
            $hall = Hall::query()
                ->withoutTrashed()
                ->whereKey($data['hall_id'])
                ->where('is_active', true)
                ->first();

            if (! $hall instanceof Hall) {
                $errors['hall_id'] = __('lecture-session.hall_must_be_active');
            }
        }

        $startTime = self::normalizedTime($data['start_time'] ?? null);
        $endTime = self::normalizedTime($data['end_time'] ?? null);
        if ($startTime && $endTime && $endTime <= $startTime) {
            $errors['end_time'] = __('lecture-session.recurring_time_range_invalid');
        }

        if ($term instanceof AcademicTerm && filled($data['session_date'] ?? null)) {
            $date = CarbonImmutable::parse($data['session_date'])->toDateString();
            $start = $term->teaching_start_date?->toDateString();
            $end = $term->teaching_end_date?->toDateString();
            $insideTeachingPeriod = $start && $end && $date >= $start && $date <= $end;

            if (! $insideTeachingPeriod) {
                $canOverride = Gate::allows(ScheduleImportRowPolicy::OVERRIDE_LECTURE_SESSION_TEACHING_PERIOD);
                $reason = trim((string) ($data['teaching_period_override_reason'] ?? ''));

                if (! $canOverride || mb_strlen($reason) < 5) {
                    $errors['session_date'] = __('lecture-session.session_date_outside_teaching_period');
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function normalizedTime(mixed $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        return substr((string) $time, 0, 5);
    }
}
