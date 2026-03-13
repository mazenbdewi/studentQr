<?php

namespace App\Filament\Resources\FailedAttempts\Pages;

use App\Filament\Resources\FailedAttempts\FailedAttemptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFailedAttempts extends ListRecords
{
    protected static string $resource = FailedAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
