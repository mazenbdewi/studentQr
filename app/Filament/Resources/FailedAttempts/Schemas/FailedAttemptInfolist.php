<?php

namespace App\Filament\Resources\FailedAttempts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FailedAttemptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
            TextEntry::make('student_id')
                ->label(__('failed_attempt.student_id'))
                ->numeric()
                ->placeholder(__('failed_attempt.not_available')),
            TextEntry::make('lecture_session_id')
                ->label(__('failed_attempt.lecture_session_id'))
                ->numeric()
                ->placeholder(__('failed_attempt.not_available')),
            TextEntry::make('reason')
                ->label(__('failed_attempt.reason'))
                ->badge()
                ->formatStateUsing(fn ($state) => __("failed_attempt.reason_{$state}")),
            TextEntry::make('ip_address')
                ->label(__('failed_attempt.ip_address'))
                ->placeholder(__('failed_attempt.not_available')),
            TextEntry::make('description')
                ->label(__('failed_attempt.description'))
                ->placeholder(__('failed_attempt.not_available'))
                ->columnSpanFull(),
            TextEntry::make('created_at')
                ->label(__('failed_attempt.created_at'))
                ->dateTime()
                ->placeholder(__('failed_attempt.not_available')),
            TextEntry::make('updated_at')
                ->label(__('failed_attempt.updated_at'))
                ->dateTime()
                ->placeholder(__('failed_attempt.not_available')),
        ]);
    }
}
