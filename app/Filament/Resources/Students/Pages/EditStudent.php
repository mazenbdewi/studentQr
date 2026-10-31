<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Support\AcademicTermContext;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected array $originalAuditAttributes = [];

    protected array $selectedSubjectIds = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'students', 'student_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'students', 'student_force_deleted', ['force' => true]))
                ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalAuditAttributes = $this->getRecord()->getOriginal();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedSubjectIds = array_values(array_filter((array) ($data['subject_ids'] ?? [])));

        unset($data['subject_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncSubjects();

        app(ActivityLogger::class)->logModelUpdated(
            $this->getRecord(),
            $this->originalAuditAttributes,
            'students',
            'student_updated'
        );
    }

    protected function syncSubjects(): void
    {
        $student = $this->getRecord();
        assert($student instanceof Student);
        $academicTermId = app(AcademicTermContext::class)->currentId();

        if ($academicTermId === null) {
            return;
        }

        $student->enrollments()
            ->where('academic_term_id', $academicTermId)
            ->whereNotIn('subject_id', $this->selectedSubjectIds ?: [0])
            ->delete();

        foreach (Subject::query()->whereKey($this->selectedSubjectIds)->get(['id', 'level']) as $subject) {
            Enrollment::query()->updateOrCreate(
                [
                    'academic_term_id' => $academicTermId,
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'semester' => null,
                    'year' => $subject->level,
                    'status' => Enrollment::STATUS_ENROLLED,
                ],
            );
        }
    }

    public function getRelations(): array
    {

        return [
            SubjectsRelationManager::class,
        ];
    }
}
