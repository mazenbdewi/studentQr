<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
    protected array $originalAuditAttributes = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'users', 'user_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'users', 'user_force_deleted', ['force' => true])),
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
        $originalRole = $this->originalAuditAttributes['role'] ?? null;

        $user->syncSystemRole($user->role);

        app(ActivityLogger::class)->logModelUpdated(
            $user,
            $this->originalAuditAttributes,
            'users',
            'user_updated'
        );

        if ($originalRole !== $user->role) {
            app(ActivityLogger::class)->logRoleChange(
                $user,
                ['role' => $originalRole],
                ['role' => $user->role],
                'user_role_changed'
            );
        }
    }

    protected function beforeSave(): void
    {
        $this->originalAuditAttributes = $this->getRecord()->getOriginal();
    }
}
