<?php

namespace App\Filament\Resources\LectureSessions;

use App\Filament\Resources\LectureSessions\RelationManagers\AbsentStudentsRelationManager;
use App\Filament\Resources\LectureSessions\RelationManagers\AttendancesRelationManager;
use App\Models\LectureSession;
use App\Models\Subject;
use BackedEnum;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LectureSessionResource extends Resource
{
    protected static ?string $model = LectureSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('lecture-session.singular');
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
                    ->relationship('subject', 'name')
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
                    ->relationship('hall', 'name')
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
                    ->required(),

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
                    ->relationship('lecturer', 'name')
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
                    ->label(__('lecture-session.actual_attendance')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject')
                    ->label(__('lecture-session.subject'))
                    ->relationship('subject', 'name'),

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
                    })
                    ->visible(fn (LectureSession $record) => $record->status === 'scheduled'),

                \Filament\Actions\Action::make('end')
                    ->label(__('lecture-session.end_session'))
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->action(function (LectureSession $record) {
                        $record->update([
                            'status' => 'completed',
                            'actual_end' => now(),
                            'qr_expired' => true,
                        ]);
                    })
                    ->visible(fn (LectureSession $record) => $record->status === 'active' && ! $record->qr_expired),

                ActionsAction::make('view_qr')
                    ->label(__('lecture-session.view_qr'))
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (LectureSession $record) => route('teacher.lecture-session.qr', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (LectureSession $record) => $record->status === 'active' && ! $record->qr_expired),

                ActionsAction::make('session_ended')
                    ->label(__('lecture-session.session_ended'))
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->disabled()
                    ->visible(fn (LectureSession $record) => $record->status === 'completed' || $record->qr_expired),

                ActionsAction::make('view_attendance')
                    ->label(__('attendance.view_attendance'))
                    ->color('success')
                    ->icon('heroicon-o-users')
                    ->url(fn (LectureSession $record) => LectureSessionResource::getUrl('view', [
                        'record' => $record,
                        'activeRelationManager' => 'attendances',
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
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
        $query = parent::getEloquentQuery();

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
