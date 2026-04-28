<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;
    protected array $originalAuditAttributes = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'departments', 'department_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'departments', 'department_force_deleted', ['force' => true])),
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
            'departments',
            'department_updated'
        );
    }
}
