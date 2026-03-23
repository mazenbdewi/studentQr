<?php

namespace App\Filament\Resources\Students\Tables;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;

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
                Tables\Columns\TextColumn::make('year')
                    ->label(__('student.year'))
                    ->numeric()
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
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('attendance-report')
                    ->label(__('student.attendance_report'))
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn (Student $record) => StudentResource::getUrl('attendance-report', ['record' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

