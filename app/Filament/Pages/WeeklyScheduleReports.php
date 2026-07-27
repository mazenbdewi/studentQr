<?php

namespace App\Filament\Pages;

use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Hall;
use App\Models\ImportBatch;
use App\Models\Lecturer;
use App\Models\Subject;
use App\Models\SubjectSection;
use App\Models\SubjectSectionScheduleSlot;
use App\Services\WeeklyScheduleReportService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class WeeklyScheduleReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'weekly-schedule-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;

    protected string $view = 'filament.pages.weekly-schedule-reports';

    public string $reportType = WeeklyScheduleReportService::COMPREHENSIVE;

    public ?string $academicTermId = null;

    public ?string $importBatchId = null;

    public ?string $facultyId = null;

    public ?string $departmentId = null;

    public ?string $subjectId = null;

    public ?string $sectionType = null;

    public ?string $subjectSectionId = null;

    public ?string $lecturerId = null;

    public ?string $hallId = null;

    public ?string $weekday = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $requestedFilters = app(WeeklyScheduleReportService::class)->normalizeFilters(request()->only([
            'academic_term_id', 'import_batch_id', 'faculty_id', 'department_id', 'subject_id',
            'section_type', 'subject_section_id', 'lecturer_id', 'hall_id', 'weekday',
        ]));
        $filterProperties = [
            'academic_term_id' => 'academicTermId', 'import_batch_id' => 'importBatchId',
            'faculty_id' => 'facultyId', 'department_id' => 'departmentId', 'subject_id' => 'subjectId',
            'section_type' => 'sectionType', 'subject_section_id' => 'subjectSectionId',
            'lecturer_id' => 'lecturerId', 'hall_id' => 'hallId', 'weekday' => 'weekday',
        ];

        foreach ($filterProperties as $key => $property) {
            if ($requestedFilters[$key] !== null) {
                $this->{$property} = (string) $requestedFilters[$key];
            }
        }

        $batchQuery = ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereIn('status', [ImportBatch::STATUS_COMPLETED, ImportBatch::STATUS_COMPLETED_WITH_ERRORS])
            ->whereHas('scheduleSlots')
            ->with('academicTerms:id');

        $requestedUuid = request()->query('batch');
        $batch = $this->importBatchId
            ? (clone $batchQuery)->find($this->importBatchId)
            : (is_string($requestedUuid) && $requestedUuid !== ''
                ? (clone $batchQuery)->where('uuid', $requestedUuid)->first()
                : ((clone $batchQuery)->count() === 1 ? $batchQuery->first() : null));

        if ($batch instanceof ImportBatch) {
            $this->importBatchId = (string) $batch->id;
            $this->academicTermId ??= (string) $batch->academicTerms->sole()->id;
        }
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('viewReports', SubjectSectionScheduleSlot::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('weekly-schedule.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('weekly-schedule.navigation.reports');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function getTitle(): string
    {
        return __('weekly-schedule-reports.title');
    }

    public function selectReport(string $type): void
    {
        abort_unless(WeeklyScheduleReportService::isSupportedType($type), 404);
        $this->reportType = $type;
        $this->resetTable();
    }

    public function updatedFacultyId(): void
    {
        $this->departmentId = null;
        $this->resetTable();
    }

    public function updatedAcademicTermId(): void
    {
        $this->subjectSectionId = null;
        $this->resetTable();
    }

    public function updatedSubjectId(): void
    {
        $this->subjectSectionId = null;
        $this->resetTable();
    }

    public function updatedImportBatchId(?string $value): void
    {
        $batch = filled($value)
            ? ImportBatch::query()->with('academicTerms:id')->find($value)
            : null;
        $this->academicTermId = $batch instanceof ImportBatch && $batch->academicTerms->count() === 1
            ? (string) $batch->academicTerms->first()->id
            : null;
        $this->subjectSectionId = null;
        $this->resetTable();
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'academicTermId', 'importBatchId', 'facultyId', 'departmentId', 'subjectId',
            'sectionType', 'subjectSectionId', 'lecturerId', 'hallId', 'weekday',
        ], true)) {
            $this->resetTable();
        }
    }

    public function clearFilters(): void
    {
        foreach (['academicTermId', 'importBatchId', 'facultyId', 'departmentId', 'subjectId', 'sectionType', 'subjectSectionId', 'lecturerId', 'hallId', 'weekday'] as $property) {
            $this->{$property} = null;
        }

        $this->resetTable();
    }

    /** @return array<string, int|string|null> */
    public function reportFilters(): array
    {
        return app(WeeklyScheduleReportService::class)->normalizeFilters([
            'academic_term_id' => $this->academicTermId,
            'import_batch_id' => $this->importBatchId,
            'faculty_id' => $this->facultyId,
            'department_id' => $this->departmentId,
            'subject_id' => $this->subjectId,
            'section_type' => $this->sectionType,
            'subject_section_id' => $this->subjectSectionId,
            'lecturer_id' => $this->lecturerId,
            'hall_id' => $this->hallId,
            'weekday' => $this->weekday,
        ]);
    }

    public function table(Table $table): Table
    {
        $type = $this->reportType;

        return $table
            ->query(fn () => app(WeeklyScheduleReportService::class)->slotQuery($this->reportFilters()))
            ->columns([
                TextColumn::make('academicTerm.display_name')->label(__('weekly-schedule.columns.academic_term'))->badge()->visible(fn (): bool => in_array($type, [WeeklyScheduleReportService::COMPREHENSIVE, WeeklyScheduleReportService::BY_LECTURER, WeeklyScheduleReportService::BY_WEEKDAY], true)),
                TextColumn::make('subject.department.faculty.name')->label(__('weekly-schedule.filters.faculty'))->visible(fn (): bool => $type === WeeklyScheduleReportService::COMPREHENSIVE),
                TextColumn::make('subject.department.name')->label(__('weekly-schedule.filters.department'))->visible(fn (): bool => $type === WeeklyScheduleReportService::COMPREHENSIVE),
                TextColumn::make('subject.code')->label(__('weekly-schedule.columns.subject_code'))->searchable(),
                TextColumn::make('subject.name')->label(__('weekly-schedule.columns.subject_name'))->searchable()->wrap(),
                TextColumn::make('subjectSection.code')->label(__('weekly-schedule.columns.section_code'))->badge(),
                TextColumn::make('subjectSection.section_type')->label(__('weekly-schedule.columns.section_type'))->formatStateUsing(fn (?string $state): string => Subject::subjectTypeOptions()[$state] ?? __('weekly-schedule.not_specified'))->visible(fn (): bool => $type === WeeklyScheduleReportService::COMPREHENSIVE),
                TextColumn::make('lecturer.name')->label(__('weekly-schedule.columns.lecturer'))->placeholder(__('weekly-schedule.not_specified'))->wrap(),
                TextColumn::make('hall.name')->label(__('weekly-schedule.columns.hall'))->placeholder(__('weekly-schedule.not_specified'))->wrap(),
                TextColumn::make('weekday')->label(__('weekly-schedule.columns.weekday'))->formatStateUsing(fn (int|string $state): string => app(WeeklyScheduleReportService::class)->weekdayLabel((int) $state))->badge(),
                TextColumn::make('start_time')->label(__('weekly-schedule.columns.start_time'))->formatStateUsing(fn (?string $state): string => substr((string) $state, 0, 5)),
                TextColumn::make('end_time')->label(__('weekly-schedule.columns.end_time'))->formatStateUsing(fn (?string $state): string => substr((string) $state, 0, 5)),
                TextColumn::make('section_capacity')->label(__('weekly-schedule.columns.section_capacity'))->numeric()->visible(fn (): bool => $type === WeeklyScheduleReportService::COMPREHENSIVE),
                TextColumn::make('expected_student_count')->label(__('weekly-schedule.columns.expected_students'))->numeric(),
            ])
            ->groups([
                Group::make('lecturer.name')->label(__('weekly-schedule.columns.lecturer'))->collapsible(),
                Group::make('hall.name')->label(__('weekly-schedule.columns.hall'))->collapsible(),
                Group::make('subject.name')->label(__('weekly-schedule.columns.subject_name'))->collapsible(),
                Group::make('weekday')->label(__('weekly-schedule.columns.weekday'))
                    ->getTitleFromRecordUsing(fn (SubjectSectionScheduleSlot $record): string => app(WeeklyScheduleReportService::class)->weekdayLabel($record->weekday))
                    ->collapsible(),
            ])
            ->defaultGroup(match ($type) {
                WeeklyScheduleReportService::BY_LECTURER => 'lecturer.name',
                WeeklyScheduleReportService::BY_HALL => 'hall.name',
                WeeklyScheduleReportService::BY_SUBJECT => 'subject.name',
                WeeklyScheduleReportService::BY_WEEKDAY => 'weekday',
                default => null,
            })
            ->groupingSettingsHidden($type !== WeeklyScheduleReportService::COMPREHENSIVE)
            ->defaultSort('weekday')
            ->paginated([25, 50, 100]);
    }

    public function summaryCounts(): array
    {
        return app(WeeklyScheduleReportService::class)->summary($this->reportFilters());
    }

    public function reviewCounts(): array
    {
        return app(WeeklyScheduleReportService::class)->reviewCounts($this->reportFilters());
    }

    public function activeFilterLabels(): array
    {
        return app(WeeklyScheduleReportService::class)->activeFilterLabels($this->reportFilters());
    }

    public function excelUrl(): string
    {
        return route('admin.weekly-schedule-reports.excel', ['type' => $this->reportType, ...array_filter($this->reportFilters(), fn ($value): bool => $value !== null)], false);
    }

    public function pdfUrl(): string
    {
        return route('admin.weekly-schedule-reports.pdf', ['type' => $this->reportType, ...array_filter($this->reportFilters(), fn ($value): bool => $value !== null)], false);
    }

    public function reconciliationUrl(): ?string
    {
        $batch = $this->importBatchId ? ImportBatch::find($this->importBatchId) : null;

        return $batch instanceof ImportBatch
            ? ScheduleImportIssues::getUrl(['batch' => $batch->id])
            : null;
    }

    public function academicTermOptions(): array
    {
        return AcademicTerm::orderByDesc('id')->pluck('display_name', 'id')->all();
    }

    public function importBatchOptions(): array
    {
        return ImportBatch::query()
            ->where('import_type', ImportBatch::TYPE_WEEKLY_SCHEDULE)
            ->whereIn('status', [ImportBatch::STATUS_COMPLETED, ImportBatch::STATUS_COMPLETED_WITH_ERRORS])
            ->whereHas('scheduleSlots')
            ->with('academicTerms:id,display_name')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (ImportBatch $batch): array => [
                $batch->id => collect([
                    $batch->source_filename,
                    $batch->academicTerms->first()?->display_name,
                    $batch->completed_at?->format('Y-m-d H:i'),
                ])->filter()->implode(' — '),
            ])
            ->all();
    }

    public function facultyOptions(): array
    {
        return Faculty::withoutTrashed()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function departmentOptions(): array
    {
        return Department::withoutTrashed()->when($this->facultyId, fn ($query) => $query->where('faculty_id', $this->facultyId))->orderBy('name')->pluck('name', 'id')->all();
    }

    public function subjectOptions(): array
    {
        return Subject::withoutTrashed()->orderBy('code')->get()->mapWithKeys(fn (Subject $subject): array => [$subject->id => "{$subject->code} — {$subject->name}"])->all();
    }

    public function subjectSectionOptions(): array
    {
        return SubjectSection::query()->when($this->subjectId, fn ($query) => $query->where('subject_id', $this->subjectId))->when($this->academicTermId, fn ($query) => $query->where('academic_term_id', $this->academicTermId))->orderBy('code')->pluck('code', 'id')->all();
    }

    public function lecturerOptions(): array
    {
        return Lecturer::orderBy('name')->pluck('name', 'id')->all();
    }

    public function hallOptions(): array
    {
        return Hall::withoutTrashed()->orderBy('name')->pluck('name', 'id')->all();
    }
}
