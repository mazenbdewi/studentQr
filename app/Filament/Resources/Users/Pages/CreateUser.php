<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $pendingPinCode = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingPinCode = filled($data['pin_code_plain'] ?? null)
            ? (string) $data['pin_code_plain']
            : null;

        unset($data['pin_code_plain'], $data['pin_code_plain_confirmation']);

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

        if ($this->pendingPinCode) {
            $user->forceFill([
                'pin_code' => Hash::make($this->pendingPinCode),
                'pin_enabled' => true,
                'pin_changed_at' => now(),
            ])->saveQuietly();
        }

        $user->syncSystemRole($user->role);

        app(ActivityLogger::class)->logModelCreated(
            $user,
            'users',
            'user_created'
        );

        app(ActivityLogger::class)->logRoleChange(
            $user,
            [],
            ['role' => $user->role],
            'user_role_assigned'
        );
    }
}
