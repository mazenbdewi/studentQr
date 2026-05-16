<?php

namespace App\Filament\Resources\Students\Tables;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use App\Support\StudentAttendancePdfExporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student_number')
                    ->label(__('student.student_number'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('student.name'))
                    ->searchable()
                    ->url(fn (Student $record): string => StudentResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('faculty.name')
                    ->label(__('student.faculty_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label(__('student.department_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('student.phone'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('national_number')
                    ->label(__('student.national_number'))
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('student.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('student.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('student.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label(__('student.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('attendance-report')
                    ->label(__('student.attendance_report'))
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (Student $record) => StudentResource::getUrl('attendance-report', ['record' => $record]))
                    ->openUrlInNewTab(),
                Action::make('export-attendance-pdf')
                    ->label(__('student.export_pdf'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn (Student $record) => app(StudentAttendancePdfExporter::class)->download($record)),
                EditAction::make()
                    ->visible(fn (Student $record): bool => ! $record->trashed()),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
                ]),
            ]);
    }
}
