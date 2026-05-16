<?php

namespace App\Filament\Resources\Attendances\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
//                TextColumn::make('lecture_session_id')
//                    ->label(__('attendance.lecture_session_id'))
//                    ->numeric()
//                    ->sortable(),


//                TextColumn::make('student_id')
//                    ->label(__('attendance.student_id'))
//                    ->numeric()
//                    ->sortable(),
                TextColumn::make('student.name')
                    ->label(__('attendance.student_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lectureSession.subject.name')
                    ->label(__('subjects.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lectureSession.subject.code')
                    ->label(__('subjects.code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('lectureSession.subject.subject_type')
                    ->label(__('subjects.subject_type'))
                    ->formatStateUsing(fn ($record): string => $record->lectureSession?->subjectSection?->section_type_label ?? $record->lectureSession?->subject?->subject_type_label ?? __('subjects.not_available'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('lectureSession.subjectSection.code')
                    ->label(__('subjects.section_code'))
                    ->badge()
                    ->placeholder(__('subjects.not_available')),

                TextColumn::make('attendance_token_id')
                    ->label(__('attendance.attendance_token_id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('attendance_time')
                    ->label(__('attendance.attendance_time'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('attendance_method')
                    ->label(__('attendance.attendance_method'))
                    ->badge(),
                TextColumn::make('attendance_status')
                    ->label(__('attendance.attendance_status'))
                    ->badge(),
                TextColumn::make('ip_address')
                    ->label(__('attendance.ip_address'))
                    ->searchable(),
                TextColumn::make('device_fingerprint')
                    ->label(__('attendance.device_fingerprint'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('attendance.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('attendance.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()->label(__('attendance.view')),
                EditAction::make()->label(__('attendance.edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label(__('attendance.delete_selected')),
                ]),
            ]);
    }

}
