<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = $data['role'] === 'super_admin' ? 'admin' : 'lecturer';

        if ($data['role'] !== 'course_lecturer') {
            $data['title'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $user->syncSystemRole($user->role);
    }
}
