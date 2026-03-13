<?php

namespace App\Filament\Resources\StudentDevices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StudentDeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('student_id')
                    ->numeric(),
                TextEntry::make('device_type')
                    ->badge(),
                TextEntry::make('device_name')
                    ->placeholder('-'),
                TextEntry::make('device_model')
                    ->placeholder('-'),
                TextEntry::make('operating_system')
                    ->placeholder('-'),
                TextEntry::make('browser')
                    ->placeholder('-'),
                TextEntry::make('device_fingerprint'),
                TextEntry::make('last_ip_address')
                    ->placeholder('-'),
                TextEntry::make('last_login_at')
                    ->dateTime()
                    ->placeholder('-'),
                IconEntry::make('is_trusted')
                    ->boolean(),
                IconEntry::make('is_blocked')
                    ->boolean(),
                TextEntry::make('block_reason')
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
