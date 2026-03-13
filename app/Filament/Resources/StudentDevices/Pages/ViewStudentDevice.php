<?php

namespace App\Filament\Resources\StudentDevices\Pages;

use App\Filament\Resources\StudentDevices\StudentDeviceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudentDevice extends ViewRecord
{
    protected static string $resource = StudentDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
