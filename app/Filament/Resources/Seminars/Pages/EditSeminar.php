<?php

namespace App\Filament\Resources\Seminars\Pages;

use App\Filament\Resources\Seminars\SeminarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeminar extends EditRecord
{
    protected static string $resource = SeminarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
