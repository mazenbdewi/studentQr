<?php

namespace App\Filament\Resources\Departments\Schemas;

use Illuminate\Database\Eloquent\Builder;
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
                TextInput::make('name')
                    ->label(__('department.name'))
                    ->required(),
                Select::make('faculty_id')
                    ->label(__('department.faculty'))
                    ->relationship(
                        name: 'faculty',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->withoutTrashed(),
                    )
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
