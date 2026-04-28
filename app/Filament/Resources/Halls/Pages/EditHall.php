<?php

namespace App\Filament\Resources\Halls\Pages;

use App\Filament\Resources\Halls\HallResource;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHall extends EditRecord
{
    protected static string $resource = HallResource::class;
    protected array $originalAuditAttributes = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'halls', 'hall_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'halls', 'hall_force_deleted', ['force' => true])),
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
            'halls',
            'hall_updated'
        );
    }
}
