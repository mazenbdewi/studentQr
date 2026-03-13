<?php

namespace App\Filament\Resources\LectureSessions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LectureSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('subject_id')
                    ->numeric(),
                TextEntry::make('lecturer_id')
                    ->numeric(),
                TextEntry::make('hall_id')
                    ->numeric(),
                TextEntry::make('session_date')
                    ->date(),
                TextEntry::make('start_time')
                    ->time(),
                TextEntry::make('end_time')
                    ->time(),
                TextEntry::make('actual_start')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('actual_end')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('attendance_mode')
                    ->badge(),
                TextEntry::make('qr_refresh_rate')
                    ->numeric(),
                TextEntry::make('expected_students')
                    ->numeric(),
                TextEntry::make('actual_attendance')
                    ->numeric(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
