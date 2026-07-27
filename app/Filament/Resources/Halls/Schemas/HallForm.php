<?php

namespace App\Filament\Resources\Halls\Schemas;

use App\Models\Faculty;
use App\Models\Hall;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class HallForm
{
    public static function configure(Schema $schema): Schema
    {
        $components = [
            TextInput::make('code')
                ->label(__('hall.code'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('name')
                ->label(__('hall.name'))
                ->required()
                ->maxLength(255),
        ];

        if (DatabaseSchema::hasColumn('halls', 'capacity')) {
            $components[] = TextInput::make('capacity')
                ->label(__('hall.capacity'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->placeholder(__('hall.not_specified'));
        }

        if (DatabaseSchema::hasColumn('halls', 'hall_type')) {
            $components[] = Select::make('hall_type')
                ->label(__('hall.hall_type'))
                ->options(Hall::hallTypeOptions())
                ->placeholder(__('hall.not_specified'))
                ->native(false);
        }

        if (DatabaseSchema::hasColumn('halls', 'building_name')) {
            $components[] = TextInput::make('building_name')
                ->label(__('hall.building_name'))
                ->maxLength(255)
                ->placeholder(__('hall.not_specified'));
        }

        if (DatabaseSchema::hasColumn('halls', 'faculty_id')) {
            $components[] = Select::make('faculty_id')
                ->label(__('hall.faculty'))
                ->options(fn (): array => Faculty::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->placeholder(__('hall.not_specified'))
                ->native(false);
        }

        $components[] = TextInput::make('floor')
            ->label(__('hall.floor'))
            ->numeric()
            ->integer()
            ->placeholder(__('hall.not_specified'));

        if (DatabaseSchema::hasColumn('halls', 'is_active')) {
            $components[] = Toggle::make('is_active')
                ->label(__('hall.is_active'))
                ->default(true)
                ->required();
        }

        if (DatabaseSchema::hasColumn('halls', 'notes')) {
            $components[] = Textarea::make('notes')
                ->label(__('hall.notes'))
                ->rows(3)
                ->columnSpanFull()
                ->placeholder(__('hall.not_specified'));
        }

        return $schema
            ->components($components);
    }
}
