<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
            TextInput::make('user_id')
                ->label(__('audit_logs.user_id'))
                ->numeric()
                ->default(null),
            TextInput::make('user_type')
                ->label(__('audit_logs.user_type'))
                ->default(null),
            TextInput::make('action')
                ->label(__('audit_logs.action'))
                ->required(),
            Textarea::make('description')
                ->label(__('audit_logs.description'))
                ->default(null)
                ->columnSpanFull(),
            TextInput::make('ip_address')
                ->label(__('audit_logs.ip_address'))
                ->default(null),
            TextInput::make('location')
                ->label(__('audit_logs.location'))
                ->default(null),
            Select::make('severity')
                ->label(__('audit_logs.severity'))
                ->options([
                    'info'     => __('audit_logs.severity_info'),
                    'warning'  => __('audit_logs.severity_warning'),
                    'error'    => __('audit_logs.severity_error'),
                    'critical' => __('audit_logs.severity_critical'),
                ])
                ->default('info')
                ->required(),
            Textarea::make('metadata')
                ->label(__('audit_logs.metadata'))
                ->default(null)
                ->columnSpanFull(),
        ]);
    }
}
