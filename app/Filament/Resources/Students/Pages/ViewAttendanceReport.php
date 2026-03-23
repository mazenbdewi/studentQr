<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\LectureSession;
use App\Models\Student;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ViewAttendanceReport extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = StudentResource::class;

    protected string $view = 'students.pages.attendance-report';

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return __('student.attendance_report_for', ['name' => $this->record->name]);
    }

    public function getHeaderActions(): array
    {
        return [
          
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAttendanceHistoryQuery())
            ->columns([
                Tables\Columns\TextColumn::make('lectureSession.subject.name')
                    ->label(__('lecture-session.subject'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.session_date')
                    ->label(__('lecture-session.session_date'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.start_time')
                    ->label(__('lecture-session.start_time'))
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.end_time')
                    ->label(__('lecture-session.end_time'))
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('lectureSession.lecturer.name')
                    ->label(__('lecture-session.lecturer'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('lectureSession.hall.name')
                    ->label(__('lecture-session.hall')),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('attendance.status'))
                    ->colors([
                        'success' => 'present',
                        'warning' => 'late',
                        'danger' => 'absent',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('attendance.recorded_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->label(__('lecture-session.subject'))
                    ->relationship('lectureSession.subject', 'name'),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from'),
                        DatePicker::make('date_to'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn ($q) => $q->whereDate('lecture_sessions.session_date', '>=', $data['date_from']),
                            )
                            ->when(
                                $data['date_to'],
                                fn ($q) => $q->whereDate('lecture_sessions.session_date', '<=', $data['date_to']),
                            );
                    }),
                TernaryFilter::make('status'),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->defaultSort('lectureSession.session_date', 'desc');
    }

    protected function getAttendanceHistoryQuery(): Builder
    {
        return Attendance::query()
            ->with(['lectureSession' => fn (Relation $query) => $query->with(['subject', 'lecturer', 'hall'])])
            ->where('student_id', $this->record->id)
            ->leftJoin('lecture_sessions', 'attendances.lecture_session_id', '=', 'lecture_sessions.id');
    }
}

