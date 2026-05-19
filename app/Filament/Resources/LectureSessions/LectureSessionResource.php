<?php

namespace App\Filament\Resources\LectureSessions;

use App\Filament\Resources\LectureSessions\RelationManagers\AbsentStudentsRelationManager;
use App\Filament\Resources\LectureSessions\RelationManagers\AttendancesRelationManager;
use App\Models\AppSetting;
use App\Models\LectureSession;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
            ->schema([
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
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $set('lecturer_id', static::resolveLecturerIdForSubjectAndSection($state, null));
                        $set('subject_section_id', null);
                    }),

                Forms\Components\Select::make('subject_section_id')
                    ->label(__('subjects.section_code'))
                    ->options(fn (Get $get): array => static::getSectionOptionsForSubject($get('subject_id')))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->disabled(fn (Get $get): bool => blank($get('subject_id')))
                    ->required(fn (Get $get): bool => static::subjectHasSections($get('subject_id')))
                    ->placeholder(__('subjects.select_subject_first'))
                    ->afterStateUpdated(function (callable $set, Get $get, mixed $state): void {
                        $set('lecturer_id', static::resolveLecturerIdForSubjectAndSection($get('subject_id'), $state));
                    })
                    ->live()
                    ->helperText(__('lecture-session.section_helper_text')),

                Forms\Components\Select::make('hall_id')
                    ->label(__('lecture-session.hall'))
                    ->relationship(
                        name: 'hall',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->withoutTrashed(),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\DatePicker::make('session_date')
                    ->label(__('lecture-session.session_date'))
                    ->required(),

                Forms\Components\TimePicker::make('start_time')
                    ->label(__('lecture-session.start_time'))
                    ->required(),

                Forms\Components\TimePicker::make('end_time')
                    ->label(__('lecture-session.end_time'))
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
                    ->nullable(),

                Forms\Components\Select::make('lecturer_id')
                    ->label(__('lecture-session.lecturer'))
                    ->relationship(
                        name: 'lecturer',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => auth()->user()?->hasRole('course_lecturer')
                            ? $query->withoutTrashed()->whereKey(auth()->id())
                            : $query->withoutTrashed(),
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder(__('lecture-session.subject_has_no_lecturer'))
                    ->validationMessages([
                        'required' => __('lecture-session.subject_has_no_lecturer'),
                    ])
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\Placeholder::make('missing_lecturer_warning')
                    ->label(__('lecture-session.missing_lecturer_warning_title'))
                    ->content(__('lecture-session.missing_lecturer_warning'))
                    ->visible(fn (Get $get): bool => static::shouldShowMissingLecturerWarning(
                        $get('subject_id'),
                        $get('subject_section_id'),
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
            ->poll('5s')
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
                    ->formatStateUsing(fn (LectureSession $record): string => $record->subjectSection?->section_type_label ?? $record->subject?->subject_type_label ?? __('subjects.not_available'))
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subjectSection.code')
                    ->label(__('subjects.section_code'))
                    ->badge()
                    ->placeholder(__('subjects.not_available'))
                    ->searchable(),

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
                    ->getStateUsing(fn ($record) => $record->attendances()
                        ->select('student_id')
                        ->distinct()
                        ->count('student_id')),
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
                    ->visible(fn (LectureSession $record) => ! $record->trashed()
                        && auth()->user()?->hasRole('course_lecturer') !== true
                        && $record->status === 'scheduled'
                        && ! $record->hasReachedScheduledEnd()),

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

        $query = parent::getEloquentQuery()
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
        $query->withoutTrashed();

        $user = auth()->user();

        if ($user?->hasRole('course_lecturer')) {
            $query->where(function (Builder $query) use ($user): void {
                $query
                    ->where('lecturer_id', $user->id)
                    ->orWhereHas('sections', fn (Builder $sectionsQuery) => $sectionsQuery->where('lecturer_id', $user->id));
            });
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
                        $query
                            ->where('lecturer_id', $user->id)
                            ->orWhereExists(function ($sectionsQuery) use ($user): void {
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
            $section = $subject->sections()
                ->whereKey($data['subject_section_id'])
                ->first();

            if (! $section) {
                throw ValidationException::withMessages([
                    'subject_section_id' => __('subjects.section_must_belong_to_subject'),
                ]);
            }
        }

        if ($user?->hasRole('course_lecturer') && ! static::subjectCanBeUsedByLecturer($subject, $user->id, $section)) {
            throw ValidationException::withMessages([
                'subject_id' => __('lecture-session.subject_not_assigned_to_lecturer'),
            ]);
        }

        $data['lecturer_id'] = $section?->lecturer_id
            ?? $subject->lecturer_id;

        if (blank($data['lecturer_id'])) {
            throw ValidationException::withMessages([
                'lecturer_id' => __('lecture-session.subject_has_no_lecturer'),
            ]);
        }

        return $data;
    }

    public static function getSectionOptionsForSubject(int | string | null $subjectId): array
    {
        if (blank($subjectId)) {
            return [];
        }

        $query = SubjectSection::query()
            ->where('subject_id', $subjectId)
            ->orderBy('code');

        $user = auth()->user();

        if ($user?->hasRole('course_lecturer')) {
            $subjectLecturerId = Subject::query()
                ->whereKey($subjectId)
                ->value('lecturer_id');

            if ((int) $subjectLecturerId !== (int) $user->id) {
                $query->where('lecturer_id', $user->id);
            }
        }

        return $query
            ->pluck('code', 'id')
            ->all();
    }

    public static function subjectHasSections(int | string | null $subjectId): bool
    {
        return filled($subjectId)
            && \App\Models\SubjectSection::query()
                ->where('subject_id', $subjectId)
                ->exists();
    }

    public static function resolveLecturerIdForSubjectAndSection(int | string | null $subjectId, int | string | null $sectionId): ?int
    {
        if (filled($sectionId)) {
            $section = SubjectSection::query()
                ->whereKey($sectionId)
                ->when(filled($subjectId), fn (Builder $query) => $query->where('subject_id', $subjectId))
                ->first(['id', 'lecturer_id']);

            if ($section?->lecturer_id) {
                return (int) $section->lecturer_id;
            }
        }

        if (filled($subjectId)) {
            $lecturerId = Subject::query()
                ->whereKey($subjectId)
                ->value('lecturer_id');

            return $lecturerId ? (int) $lecturerId : null;
        }

        return null;
    }

    public static function shouldShowMissingLecturerWarning(int | string | null $subjectId, int | string | null $sectionId): bool
    {
        if (blank($subjectId)) {
            return false;
        }

        if (static::subjectHasSections($subjectId) && blank($sectionId)) {
            return false;
        }

        return static::resolveLecturerIdForSubjectAndSection($subjectId, $sectionId) === null;
    }

    private static function subjectCanBeUsedByLecturer(Subject $subject, int $lecturerId, ?SubjectSection $section = null): bool
    {
        if ($section) {
            return (int) $section->lecturer_id === $lecturerId
                || (blank($section->lecturer_id) && (int) $subject->lecturer_id === $lecturerId);
        }

        return (int) $subject->lecturer_id === $lecturerId
            || $subject->sections()->where('lecturer_id', $lecturerId)->exists();
    }
}
