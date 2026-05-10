<?php

namespace App\Filament\Resources\Seminars;

use App\Filament\Resources\Seminars\Pages\CreateSeminar;
use App\Filament\Resources\Seminars\Pages\EditSeminar;
use App\Filament\Resources\Seminars\Pages\ListSeminars;
use App\Models\Seminar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeminarResource extends Resource
{
    protected static ?string $model = Seminar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('teacher.seminars');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-dashboard.navigation.daily_operations');
    }

    public static function getNavigationSort(): ?int
    {
        return 25;
    }

    public static function getModelLabel(): string
    {
        return __('teacher.seminar');
    }

    public static function getPluralModelLabel(): string
    {
        return __('teacher.seminars');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Hidden::make('created_by')
                ->default(fn () => auth()->id())
                ->required(),

            Forms\Components\TextInput::make('title')
                ->label(__('teacher.seminar_title'))
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('audience_type')
                ->label(__('teacher.audience_type'))
                ->maxLength(255),

            Forms\Components\TextInput::make('location')
                ->label(__('teacher.location'))
                ->maxLength(255),

            Forms\Components\DateTimePicker::make('starts_at')
                ->label(__('teacher.starts_at')),

            Forms\Components\DateTimePicker::make('ends_at')
                ->label(__('teacher.ends_at'))
                ->afterOrEqual('starts_at'),

            Forms\Components\Select::make('status')
                ->label(__('teacher.status'))
                ->options([
                    'draft' => __('teacher.seminar_status_draft'),
                    'active' => __('teacher.seminar_status_active'),
                    'completed' => __('teacher.seminar_status_completed'),
                    'cancelled' => __('teacher.seminar_status_cancelled'),
                ])
                ->default('draft')
                ->required(),

            Forms\Components\Textarea::make('description')
                ->label(__('teacher.description'))
                ->columnSpanFull(),

            Forms\Components\ToggleButtons::make('registration_fields')
                ->label(__('teacher.mobile_fields'))
                ->options(static::registrationFieldOptions())
                ->multiple()
                ->default(['specialization', 'profession', 'academic_rank'])
                ->columns([
                    'sm' => 2,
                    'md' => 4,
                    'xl' => 8,
                ])
                ->colors(array_fill_keys(array_keys(static::registrationFieldOptions()), 'primary'))
                ->extraAttributes(['class' => 'seminar-registration-toggle-buttons lecture-weekday-toggle-buttons'])
                ->live()
                ->afterStateHydrated(function (Forms\Components\ToggleButtons $component): void {
                    /** @var Seminar|null $record */
                    $record = $component->getRecord();

                    if (! $record) {
                        return;
                    }

                    $component->state(static::registrationFieldsFromRecord($record));
                })
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    static::syncRegistrationFieldState($set, (array) $state);
                })
                ->helperText(__('teacher.mobile_fields_help'))
                ->dehydrated(false)
                ->columnSpanFull(),

            Forms\Components\Hidden::make('collect_specialization')
                ->default(true),

            Forms\Components\Hidden::make('collect_profession')
                ->default(true),

            Forms\Components\Hidden::make('collect_academic_rank')
                ->default(true),

            Forms\Components\Hidden::make('collect_age')
                ->default(false),

            Forms\Components\Hidden::make('collect_organization')
                ->default(false),

            Forms\Components\Hidden::make('collect_phone')
                ->default(false),

            Forms\Components\Hidden::make('collect_email')
                ->default(false),

            Forms\Components\Hidden::make('collect_notes')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('teacher.seminar_title'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('audience_type')
                    ->label(__('teacher.audience_type'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('location')
                    ->label(__('teacher.location'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('teacher.status'))
                    ->formatStateUsing(fn (string $state): string => __('teacher.seminar_status_'.$state))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('attendances_count')
                    ->label(__('teacher.attendees'))
                    ->counts('attendances')
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('teacher.starts_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('teacher_page')
                    ->label(__('teacher.view'))
                    ->url(fn (Seminar $record): string => route('teacher.seminars.show', $record))
                    ->openUrlInNewTab(),
                Action::make('start_qr')
                    ->label(__('teacher.start_qr'))
                    ->url(fn (Seminar $record): string => route('teacher.seminars.open-qr', $record))
                    ->visible(fn (Seminar $record): bool => $record->status !== 'active' || $record->qr_expired || ! $record->qr_token)
                    ->openUrlInNewTab(),
                Action::make('qr')
                    ->label(__('teacher.show_qr'))
                    ->url(fn (Seminar $record): string => route('teacher.seminars.qr', $record))
                    ->visible(fn (Seminar $record): bool => $record->status === 'active' && ! $record->qr_expired && filled($record->qr_token))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeminars::route('/'),
            'create' => CreateSeminar::route('/create'),
            'edit' => EditSeminar::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->hasRole('course_lecturer')) {
            $query->where('created_by', auth()->id());
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'admin', 'manager', 'course_lecturer']) ?? false;
    }

    protected static function registrationFieldOptions(): array
    {
        return [
            'specialization' => __('teacher.specialization'),
            'profession' => __('teacher.profession'),
            'academic_rank' => __('teacher.academic_rank'),
            'age' => __('teacher.age'),
            'organization' => __('teacher.organization'),
            'phone' => __('teacher.phone'),
            'email' => __('teacher.email'),
            'notes' => __('teacher.notes'),
        ];
    }

    protected static function registrationFieldsFromRecord(Seminar $record): array
    {
        return collect([
            'specialization' => $record->collect_specialization,
            'profession' => $record->collect_profession,
            'academic_rank' => $record->collect_academic_rank,
            'age' => $record->collect_age,
            'organization' => $record->collect_organization,
            'phone' => $record->collect_phone,
            'email' => $record->collect_email,
            'notes' => $record->collect_notes,
        ])
            ->filter()
            ->keys()
            ->all();
    }

    protected static function syncRegistrationFieldState(Set $set, array $selectedFields): void
    {
        foreach (array_keys(static::registrationFieldOptions()) as $field) {
            $set('collect_'.$field, in_array($field, $selectedFields, true));
        }
    }
}
