<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('department.code'))
                    ->required(),
                TextInput::make('name')
                    ->label(__('department.name_ar'))
                    ->required(),
                TextInput::make('name_en')
                    ->label(__('department.name_en'))
                    ->default(null),
                Select::make('faculty_id')
                    ->label(__('department.faculty'))
                    ->relationship('faculty', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('description')
                    ->label(__('department.description'))
                    ->default(null)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label(__('department.is_active'))
                    ->required(),
            ]);
    }
}
