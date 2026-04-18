<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Subject;
use App\Support\StudentAttendancePdfExporter;
use App\Support\StudentAttendanceReport;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ViewAttendanceReport extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = StudentResource::class;

    protected string $view = 'students.pages.attendance-report';

    public function mount($record): void
    {
        parent::mount($record);
    }

    public function getTitle(): string
    {
        if ($selectedSubject = $this->getSelectedSubject()) {
            return __('student.subject_attendance_report_for', [
                'name' => $this->record->name,
                'subject' => $selectedSubject->name,
            ]);
        }

        return __('student.attendance_report_for', ['name' => $this->record->name]);
    }

    public function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_pdf')
                ->label(__('student.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => app(StudentAttendancePdfExporter::class)->download(
                    $this->record,
                    $this->getSelectedSubject()?->id,
                )),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAttendanceReport()->query($this->record))
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')
                    ->label(__('student.lecture'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('session_date')
                    ->label(__('student.day_date'))
                    ->formatStateUsing(fn ($state) => $state ? $state->translatedFormat('l, Y-m-d') : __('lecture-session.not_available'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('session_time')
                    ->label(__('student.time'))
                    ->state(fn ($record): string => $this->formatSessionTime($record->start_time, $record->end_time)),
                Tables\Columns\BadgeColumn::make('report_status')
                    ->label(__('attendance.status'))
                    ->formatStateUsing(fn (string $state): string => $state === 'present'
                        ? __('attendance.status_present')
                        : __('attendance.status_absent'))
                    ->colors([
                        'success' => 'present',
                        'danger' => 'absent',
                    ]),
                Tables\Columns\TextColumn::make('attendance_recorded_at')
                    ->label(__('attendance.recorded_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('subject_id')
                    ->label(__('lecture-session.subject'))
                    ->options(fn (): array => $this->getAttendanceReport()->subjectOptions($this->record))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $rawSubjectId = $data['value'] ?? null;

                        if (blank($rawSubjectId)) {
                            return $query;
                        }

                        $subjectId = $this->normalizeSelectedSubjectId($rawSubjectId);

                        if ($subjectId === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->where('lecture_sessions.subject_id', $subjectId);
                    }),
                Tables\Filters\Filter::make('date_range')
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
                SelectFilter::make('report_status')
                    ->label(__('attendance.status'))
                    ->options([
                        'present' => __('attendance.status_present'),
                        'absent' => __('attendance.status_absent'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'present' => $query
                                ->whereNotNull('student_attendances.id')
                                ->whereNotIn('student_attendances.attendance_status', ['pending', 'absent']),
                            'absent' => $query->where(function (Builder $subQuery): void {
                                $subQuery
                                    ->whereNull('student_attendances.id')
                                    ->orWhereIn('student_attendances.attendance_status', ['pending', 'absent']);
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading(__('student.no_attendance_history'))
            ->defaultSort('session_date', 'desc');
    }

    public function getSummary(): array
    {
        return $this->getAttendanceReport()->summaryFromRows($this->getFilteredReportRows());
    }

    public function getSelectedSubject(): ?Subject
    {
        return $this->getAttendanceReport()->resolveSubject(
            $this->record,
            $this->normalizeSelectedSubjectId($this->getTableFilterState('subject_id')['value'] ?? null),
        );
    }

    public function getSelectedSubjectLabel(): string
    {
        return $this->getSelectedSubject()?->name ?? __('enrollments.enrolled_subjects');
    }

    private function getAttendanceReport(): StudentAttendanceReport
    {
        return app(StudentAttendanceReport::class);
    }

    private function getFilteredReportRows(): Collection
    {
        return $this->getFilteredTableQuery()?->get() ?? collect();
    }

    private function formatSessionTime(?string $startTime, ?string $endTime): string
    {
        if (! $startTime || ! $endTime) {
            return __('lecture-session.not_available');
        }

        return \Illuminate\Support\Carbon::parse($startTime)->format('H:i')
            .' - '
            .\Illuminate\Support\Carbon::parse($endTime)->format('H:i');
    }

    private function normalizeSelectedSubjectId(mixed $subjectId): ?int
    {
        if (blank($subjectId) || ! is_numeric($subjectId)) {
            return null;
        }

        $subjectId = (int) $subjectId;
        $validSubjectIds = collect($this->getAttendanceReport()->subjectOptions($this->record))
            ->keys()
            ->map(fn (mixed $id): int => (int) $id);

        return $validSubjectIds->contains($subjectId) ? $subjectId : null;
    }
}
