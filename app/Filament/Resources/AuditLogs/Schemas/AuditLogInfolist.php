<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {return $schema
        ->components([
            TextEntry::make('user_id')
                ->label(__('audit_logs.user_id'))
                ->numeric()
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('user_type')
                ->label(__('audit_logs.user_type'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('action')
                ->label(__('audit_logs.action')),
            TextEntry::make('description')
                ->label(__('audit_logs.description'))
                ->placeholder(__('audit_logs.not_available'))
                ->columnSpanFull(),
            TextEntry::make('ip_address')
                ->label(__('audit_logs.ip_address'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('location')
                ->label(__('audit_logs.location'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('severity')
                ->label(__('audit_logs.severity'))
                ->badge()
                ->formatStateUsing(fn ($state) => __("audit_logs.severity_{$state}")),
            TextEntry::make('metadata')
                ->label(__('audit_logs.metadata'))
                ->placeholder(__('audit_logs.not_available'))
                ->columnSpanFull(),
            TextEntry::make('created_at')
                ->label(__('audit_logs.created_at'))
                ->dateTime()
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('updated_at')
                ->label(__('audit_logs.updated_at'))
                ->dateTime()
                ->placeholder(__('audit_logs.not_available')),
        ]);
        
    }
}
