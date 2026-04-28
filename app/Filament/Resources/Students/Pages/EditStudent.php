<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\Students\StudentResource;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;
    protected array $originalAuditAttributes = [];

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

    protected function afterSave(): void
    {
        app(ActivityLogger::class)->logModelUpdated(
            $this->getRecord(),
            $this->originalAuditAttributes,
            'students',
            'student_updated'
        );
    }

    public function getRelations(): array
    {

        return [
            SubjectsRelationManager::class,
        ];
    }
}
