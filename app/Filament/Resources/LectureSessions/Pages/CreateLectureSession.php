<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Services\ActivityLogger;
use App\Support\AcademicTermContext;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateLectureSession extends CreateRecord
{
    protected static string $resource = LectureSessionResource::class;

    /** @var array<string, string> */
    protected array $overrideAuditContext = [];

    public function mount(): void
    {
        if (! app(AcademicTermContext::class)->hasCurrent()) {
            Notification::make()
                ->title(__('academic-term.no_current'))
                ->warning()
                ->send();
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $term = app(AcademicTermContext::class)->currentOrNull();

        if (! $term) {
            throw ValidationException::withMessages([
                'academic_term_id' => __('academic-term.no_current'),
            ]);
        }

        $data['academic_term_id'] = $term->id;
        $data = LectureSessionResource::ensureSubjectCanBeUsedByCurrentUser($data);
        $this->overrideAuditContext = LectureSessionResource::teachingPeriodOverrideAuditContext($data);

        return LectureSessionResource::prepareManualSessionData($data);
    }

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->logModelCreated(
            $this->getRecord(),
            'lecture_sessions',
            'lecture_session_created',
            $this->overrideAuditContext,
        );
    }
}
