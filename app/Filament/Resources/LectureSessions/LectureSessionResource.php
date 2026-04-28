<?php

namespace App\Filament\Resources\LectureSessions;

use App\Filament\Resources\LectureSessions\RelationManagers\AbsentStudentsRelationManager;
use App\Filament\Resources\LectureSessions\RelationManagers\AttendancesRelationManager;
use App\Models\LectureSession;
use App\Models\Subject;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                        modifyQueryUsing: fn (Builder $query) => $query->withoutTrashed(),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $subject = Subject::find($state);
                        $set('lecturer_id', $subject?->lecturer_id ?? auth()->id());
                    }),

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
                    ->default(120)
                    ->suffix(__('lecture-session.seconds')),

                Forms\Components\Textarea::make('notes')
                    ->label(__('lecture-session.notes'))
                    ->nullable(),

                Forms\Components\Select::make('lecturer_id')
                    ->label(__('lecture-session.lecturer'))
                    ->relationship(
                        name: 'lecturer',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->withoutTrashed(),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled()
                    ->dehydrated(),
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
                        modifyQueryUsing: fn (Builder $query) => $query->withoutTrashed(),
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
                    ->visible(fn (LectureSession $record) => ! $record->trashed() && $record->status === 'scheduled' && ! $record->hasReachedScheduledEnd()),

                \Filament\Actions\Action::make('end')
                    ->label(__('lecture-session.end_session'))
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->action(function (LectureSession $record) {
                        $original = $record->getOriginal();
                        $record->syncLifecycleState();

                        if ($record->status !== 'active' || $record->qr_expired) {
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
                    ->visible(fn (LectureSession $record) => ! $record->trashed() && $record->status === 'active' && ! $record->qr_expired && ! $record->hasReachedScheduledEnd()),

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
                    ->visible(fn (LectureSession $record) => ! $record->trashed() && ($record->status === 'completed' || $record->qr_expired || $record->hasReachedScheduledEnd())),

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
                    ->visible(fn (LectureSession $record): bool => ! $record->trashed()),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
            ])->defaultSort('session_date', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
        return auth()->user()->hasAnyRole(['super-admin', 'manager', 'course_lecturer']);
    }
}
