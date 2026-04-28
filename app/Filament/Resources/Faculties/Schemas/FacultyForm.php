<?php

namespace App\Filament\Resources\Faculties\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FacultyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('faculty.name_ar'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label(__('faculty.description'))
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('faculty.is_active'))
                    ->required()
                    ->default(true),
            ]);
    }
}
