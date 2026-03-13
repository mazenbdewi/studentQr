<?php

namespace App\Filament\Resources\FailedAttempts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class FailedAttemptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
    ->components([
        TextInput::make('student_id')
            ->label(__('failed_attempt.student_id'))
            ->numeric()
            ->default(null),
        TextInput::make('lecture_session_id')
            ->label(__('failed_attempt.lecture_session_id'))
            ->numeric()
            ->default(null),
        Select::make('reason')
            ->label(__('failed_attempt.reason'))
            ->options([
                'wrong_otp' => __('failed_attempt.reason_wrong_otp'),
                'wrong_ip' => __('failed_attempt.reason_wrong_ip'),
                'device_blocked' => __('failed_attempt.reason_device_blocked'),
                'duplicate_device' => __('failed_attempt.reason_duplicate_device'),
                'late' => __('failed_attempt.reason_late'),
                'other' => __('failed_attempt.reason_other'),
            ])
            ->default('other')
            ->required(),
        TextInput::make('ip_address')
            ->label(__('failed_attempt.ip_address'))
            ->default(null),
        Textarea::make('description')
            ->label(__('failed_attempt.description'))
            ->default(null)
            ->columnSpanFull(),
    ]);
    }
}
