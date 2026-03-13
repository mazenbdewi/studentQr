<?php

namespace App\Filament\Resources\FailedAttempts\Pages;

use App\Filament\Resources\FailedAttempts\FailedAttemptResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFailedAttempt extends EditRecord
{
    protected static string $resource = FailedAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
