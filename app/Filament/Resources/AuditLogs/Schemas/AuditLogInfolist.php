<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {return $schema
        ->components([
            TextEntry::make('user.name')
                ->label(__('audit_logs.user'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('category')
                ->label(__('audit_logs.category'))
                ->formatStateUsing(fn (?string $state): string => filled($state) ? __("audit_logs.categories.{$state}") : __('audit_logs.not_available'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('action')
                ->label(__('audit_logs.action')),
            TextEntry::make('model_type')
                ->label(__('audit_logs.model'))
                ->formatStateUsing(fn (?string $state): string => filled($state) ? class_basename($state) : __('audit_logs.not_available'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('model_id')
                ->label(__('audit_logs.model_id'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('description')
                ->label(__('audit_logs.description'))
                ->placeholder(__('audit_logs.not_available'))
                ->columnSpanFull(),
            TextEntry::make('ip_address')
                ->label(__('audit_logs.ip_address'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('user_agent')
                ->label(__('audit_logs.user_agent'))
                ->placeholder(__('audit_logs.not_available')),
            TextEntry::make('severity')
                ->label(__('audit_logs.severity'))
                ->badge()
                ->formatStateUsing(fn ($state) => __("audit_logs.severity_{$state}")),
            TextEntry::make('old_values')
                ->label(__('audit_logs.old_values'))
                ->state(fn ($record) => $record->old_values ? json_encode($record->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                ->placeholder(__('audit_logs.not_available'))
                ->columnSpanFull(),
            TextEntry::make('new_values')
                ->label(__('audit_logs.new_values'))
                ->state(fn ($record) => $record->new_values ? json_encode($record->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                ->placeholder(__('audit_logs.not_available'))
                ->columnSpanFull(),
            TextEntry::make('context')
                ->label(__('audit_logs.context'))
                ->state(fn ($record) => $record->context ? json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                ->placeholder(__('audit_logs.not_available'))
                ->columnSpanFull(),
            TextEntry::make('created_at')
                ->label(__('audit_logs.created_at'))
                ->dateTime()
                ->placeholder(__('audit_logs.not_available')),
        ]);
        
    }
}
