<?php

namespace App\Filament\Resources\LectureSessions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class LectureSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject_id')
                    ->required()
                    ->numeric(),
                TextInput::make('lecturer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('hall_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('session_date')
                    ->required(),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                DateTimePicker::make('actual_start'),
                DateTimePicker::make('actual_end'),
                Select::make('status')
                    ->options([
            'scheduled' => 'Scheduled',
            'active' => 'Active',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ])
                    ->default('scheduled')
                    ->required(),
                Select::make('attendance_mode')
                    ->options(['qr_only' => 'Qr only', 'qr_otp' => 'Qr otp', 'manual' => 'Manual'])
                    ->default('qr_otp')
                    ->required(),
                TextInput::make('qr_refresh_rate')
                    ->required()
                    ->numeric()
                    ->default(120),
                TextInput::make('expected_students')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('actual_attendance')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('notes')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
