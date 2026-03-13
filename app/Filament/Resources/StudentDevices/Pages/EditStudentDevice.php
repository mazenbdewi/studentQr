<?php

namespace App\Filament\Resources\StudentDevices\Pages;

use App\Filament\Resources\StudentDevices\StudentDeviceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentDevice extends EditRecord
{
    protected static string $resource = StudentDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
