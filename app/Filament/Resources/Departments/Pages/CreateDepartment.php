<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->logModelCreated(
            $this->getRecord(),
            'departments',
            'department_created'
        );
    }
}
