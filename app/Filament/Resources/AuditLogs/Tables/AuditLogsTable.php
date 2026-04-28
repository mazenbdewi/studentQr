<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->defaultSort('created_at', 'desc')
        ->columns([
            \Filament\Tables\Columns\TextColumn::make('user.name')
                ->label(__('audit_logs.user'))
                ->searchable()
                ->sortable(),
            BadgeColumn::make('category')
                ->label(__('audit_logs.category'))
                ->formatStateUsing(fn (?string $state): string => filled($state) ? __("audit_logs.categories.{$state}") : __('audit_logs.not_available'))
                ->colors([
                    'primary' => 'auth',
                    'success' => 'students',
                    'warning' => 'attendance',
                    'info' => 'reports',
                    'danger' => 'permissions',
                    'gray' => fn (?string $state): bool => ! in_array($state, ['auth', 'students', 'attendance', 'reports', 'permissions'], true),
                ]),
            BadgeColumn::make('action')
                ->label(__('audit_logs.action'))
                ->formatStateUsing(fn (?string $state): string => filled($state) ? __("audit_logs.actions.{$state}") : __('audit_logs.not_available'))
                ->colors([
                    'success' => 'create',
                    'warning' => 'update',
                    'danger' => ['delete', 'failed_login', 'failed_attendance_attempt'],
                    'info' => ['export', 'login', 'logout', 'attendance_registered', 'qr_shown', 'qr_regenerated', 'qr_expired', 'settings_changed', 'role_changed'],
                    'primary' => 'import',
                ]),
            \Filament\Tables\Columns\TextColumn::make('model_type')
                ->label(__('audit_logs.model'))
                ->formatStateUsing(fn (?string $state): string => filled($state) ? class_basename($state) : __('audit_logs.not_available'))
                ->searchable(),
            \Filament\Tables\Columns\TextColumn::make('model_id')
                ->label(__('audit_logs.model_id'))
                ->sortable(),
            \Filament\Tables\Columns\TextColumn::make('description')
                ->label(__('audit_logs.description'))
                ->limit(80)
                ->searchable(),
            \Filament\Tables\Columns\TextColumn::make('ip_address')
                ->label(__('audit_logs.ip_address'))
                ->searchable(),
            \Filament\Tables\Columns\TextColumn::make('severity')
                ->label(__('audit_logs.severity'))
                ->badge()
                ->formatStateUsing(fn ($state) => __("audit_logs.severity_{$state}")),
            \Filament\Tables\Columns\TextColumn::make('created_at')
                ->label(__('audit_logs.created_at'))
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            Filter::make('created_at')
                ->label(__('audit_logs.date_range'))
                ->form([
                    DatePicker::make('from')->label(__('audit_logs.date_from')),
                    DatePicker::make('until')->label(__('audit_logs.date_to')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                }),
            SelectFilter::make('user_id')
                ->label(__('audit_logs.user'))
                ->relationship('user', 'name')
                ->searchable()
                ->preload(),
            SelectFilter::make('action')
                ->label(__('audit_logs.action'))
                ->options([
                    'create' => __('audit_logs.actions.create'),
                    'update' => __('audit_logs.actions.update'),
                    'delete' => __('audit_logs.actions.delete'),
                    'import' => __('audit_logs.actions.import'),
                    'export' => __('audit_logs.actions.export'),
                    'login' => __('audit_logs.actions.login'),
                    'logout' => __('audit_logs.actions.logout'),
                    'failed_login' => __('audit_logs.actions.failed_login'),
                    'attendance_registered' => __('audit_logs.actions.attendance_registered'),
                    'failed_attendance_attempt' => __('audit_logs.actions.failed_attendance_attempt'),
                    'settings_changed' => __('audit_logs.actions.settings_changed'),
                    'role_changed' => __('audit_logs.actions.role_changed'),
                    'qr_shown' => __('audit_logs.actions.qr_shown'),
                    'qr_regenerated' => __('audit_logs.actions.qr_regenerated'),
                    'qr_expired' => __('audit_logs.actions.qr_expired'),
                ]),
            SelectFilter::make('category')
                ->label(__('audit_logs.category'))
                ->options([
                    'auth' => __('audit_logs.categories.auth'),
                    'students' => __('audit_logs.categories.students'),
                    'subjects' => __('audit_logs.categories.subjects'),
                    'lecture_sessions' => __('audit_logs.categories.lecture_sessions'),
                    'attendance' => __('audit_logs.categories.attendance'),
                    'reports' => __('audit_logs.categories.reports'),
                    'settings' => __('audit_logs.categories.settings'),
                    'permissions' => __('audit_logs.categories.permissions'),
                    'departments' => __('audit_logs.categories.departments'),
                    'faculties' => __('audit_logs.categories.faculties'),
                    'halls' => __('audit_logs.categories.halls'),
                    'users' => __('audit_logs.categories.users'),
                ]),
            SelectFilter::make('model_type')
                ->label(__('audit_logs.model'))
                ->options([
                    \App\Models\Student::class => 'Student',
                    \App\Models\Subject::class => 'Subject',
                    \App\Models\LectureSession::class => 'LectureSession',
                    \App\Models\User::class => 'User',
                    \App\Models\Department::class => 'Department',
                    \App\Models\Faculty::class => 'Faculty',
                    \App\Models\Hall::class => 'Hall',
                ]),
        ])
        ->recordActions([
            ViewAction::make()->label(__('audit_logs.view')),
        ])
        ->toolbarActions([]);
    }
}
