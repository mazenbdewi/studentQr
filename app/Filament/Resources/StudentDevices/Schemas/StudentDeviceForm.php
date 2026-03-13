<?php

namespace App\Filament\Resources\StudentDevices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StudentDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('student_id')
                    ->required()
                    ->numeric(),
                Select::make('device_type')
                    ->options(['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'])
                    ->default('mobile')
                    ->required(),
                TextInput::make('device_name')
                    ->default(null),
                TextInput::make('device_model')
                    ->default(null),
                TextInput::make('operating_system')
                    ->default(null),
                TextInput::make('browser')
                    ->default(null),
                TextInput::make('device_fingerprint')
                    ->required(),
                TextInput::make('last_ip_address')
                    ->default(null),
                DateTimePicker::make('last_login_at'),
                Toggle::make('is_trusted')
                    ->required(),
                Toggle::make('is_blocked')
                    ->required(),
                Textarea::make('block_reason')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
