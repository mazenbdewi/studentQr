<?php

namespace App\Filament\Resources\FailedAttempts\Pages;

use App\Filament\Resources\FailedAttempts\FailedAttemptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFailedAttempt extends CreateRecord
{
    protected static string $resource = FailedAttemptResource::class;
}
