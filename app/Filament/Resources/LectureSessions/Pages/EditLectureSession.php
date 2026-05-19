<?php

namespace App\Filament\Resources\LectureSessions\Pages;

use App\Filament\Resources\LectureSessions\LectureSessionResource;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLectureSession extends EditRecord
{
    protected static string $resource = LectureSessionResource::class;
    protected array $originalAuditAttributes = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => LectureSessionResource::canCurrentUserDeleteLectureSession($this->getRecord()))
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'lecture_sessions', 'lecture_session_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'lecture_sessions', 'lecture_session_force_deleted', ['force' => true]))
                ->visible(fn (): bool => auth()->user()->hasRole('super-admin')),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalAuditAttributes = $this->getRecord()->getOriginal();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LectureSessionResource::ensureSubjectCanBeUsedByCurrentUser($data);
    }

    protected function afterSave(): void
    {
        app(ActivityLogger::class)->logModelUpdated(
            $this->getRecord(),
            $this->originalAuditAttributes,
            'lecture_sessions',
            'lecture_session_updated'
        );
    }
}
