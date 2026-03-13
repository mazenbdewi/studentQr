<?php

namespace App\Filament\Resources\StudentDevices\Pages;

use App\Filament\Resources\StudentDevices\StudentDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentDevices extends ListRecords
{
    protected static string $resource = StudentDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
