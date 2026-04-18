<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'active';
        $data['type'] = $data['role'] === 'super_admin' ? 'admin' : 'lecturer';

        if ($data['role'] !== 'course_lecturer') {
            $data['title'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $user->syncSystemRole($user->role);
    }
}
