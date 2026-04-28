<?php

namespace App\Filament\Resources\Faculties\Pages;

use App\Filament\Resources\Faculties\FacultyResource;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFaculty extends EditRecord
{
    protected static string $resource = FacultyResource::class;
    protected array $originalAuditAttributes = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'faculties', 'faculty_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'faculties', 'faculty_force_deleted', ['force' => true])),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalAuditAttributes = $this->getRecord()->getOriginal();
    }

    protected function afterSave(): void
    {
        app(ActivityLogger::class)->logModelUpdated(
            $this->getRecord(),
            $this->originalAuditAttributes,
            'faculties',
            'faculty_updated'
        );
    }
}
