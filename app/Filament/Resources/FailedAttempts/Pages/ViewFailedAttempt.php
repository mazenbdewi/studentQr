<?php

namespace App\Filament\Resources\FailedAttempts\Pages;

use App\Filament\Resources\FailedAttempts\FailedAttemptResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFailedAttempt extends ViewRecord
{
    protected static string $resource = FailedAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
