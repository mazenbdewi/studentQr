<?php

namespace App\Filament\Resources\Attendances\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
            TextInput::make('lecture_session_id')
                ->label(__('attendance.lecture_session_id'))
                ->required()
                ->numeric(),
            TextInput::make('student_id')
                ->label(__('attendance.student_id'))
                ->required()
                ->numeric(),
            TextInput::make('attendance_token_id')
                ->label(__('attendance.attendance_token_id'))
                ->numeric()
                ->default(null),
            DateTimePicker::make('attendance_time')
                ->label(__('attendance.attendance_time'))
                ->required(),
            Select::make('attendance_method')
                ->label(__('attendance.attendance_method'))
                ->options([
                    'qr_scan' => __('attendance.method_qr_scan'),
                    'manual'  => __('attendance.method_manual'),
                    'admin'   => __('attendance.method_admin'),
                ])
                ->default('qr_scan')
                ->required(),
            Select::make('attendance_status')
                ->label(__('attendance.attendance_status'))
                ->options([
                    'present' => __('attendance.status_present'),
                    'late'    => __('attendance.status_late'),
                    'absent'  => __('attendance.status_absent'),
                    'excused' => __('attendance.status_excused'),
                ])
                ->default('present')
                ->required(),
            TextInput::make('ip_address')
                ->label(__('attendance.ip_address'))
                ->default(null),
            TextInput::make('device_fingerprint')
                ->label(__('attendance.device_fingerprint'))
                ->default(null),
            Textarea::make('location_data')
                ->label(__('attendance.location_data'))
                ->default(null)
                ->columnSpanFull(),
        ]);
    }
}
