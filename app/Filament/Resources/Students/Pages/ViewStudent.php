<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Attendance;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ViewStudent extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'students.pages.view-student';

    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAttendanceHistoryQuery())
            ->columns([
                Tables\Columns\TextColumn::make('lectureSession.subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.session_date')
                    ->label('Attendance Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Attendance Time')
                    ->dateTime('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.hall.name')
                    ->label('Hall')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.lecturer.name')
                    ->label('Lecturer')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'present',
                        'warning' => 'late',
                        'danger' => 'absent',
                    ]),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->label('Subject')
                    ->relationship('lectureSession.subject', 'name'),
                Tables\Filters\Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                       DatePicker::make('date_from'),
                      DatePicker::make('date_to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $q): Builder => $q->whereDate('lecture_sessions.session_date', '>=', $data['date_from']),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $q): Builder => $q->whereDate('lecture_sessions.session_date', '<=', $data['date_to']),
                            );
                    }),
                TernaryFilter::make('status')
                    ->label('Status'),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->defaultSort('recorded_at', 'desc')
            ->headerActions([
                ExportBulkAction::make(),
            ]);
    }

    protected function getAttendanceHistoryQuery(): Builder
    {
        return Attendance::query()
            ->with(['lectureSession' => fn (Relation $query) => $query->with(['subject', 'lecturer', 'hall'])])
            ->where('student_id', $this->record->id)
            ->leftJoin('lecture_sessions', 'attendances.lecture_session_id', '=', 'lecture_sessions.id');
    }

    public function getRelations(): array
    {
        return [
            SubjectsRelationManager::class,
        ];
    }
}

