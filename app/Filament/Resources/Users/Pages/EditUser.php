<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
    protected array $originalAuditAttributes = [];
    protected ?string $pendingPinCode = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearPinCode')
                ->label(__('user.clear_pin_code'))
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('user.clear_pin_code'))
                ->modalDescription(__('user.clear_pin_code_confirmation'))
                ->visible(fn (): bool => UserResource::canManagePins() && $this->getRecord()->hasPinCode())
                ->action(function (): void {
                    $this->getRecord()->forceFill([
                        'pin_code' => null,
                        'pin_enabled' => false,
                        'pin_changed_at' => now(),
                    ])->saveQuietly();

                    Notification::make()
                        ->title(__('user.pin_cleared_successfully'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'users', 'user_deleted')),
            RestoreAction::make(),
            ForceDeleteAction::make()
                ->after(fn () => app(ActivityLogger::class)->logModelDeleted($this->getRecord(), 'users', 'user_force_deleted', ['force' => true])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingPinCode = filled($data['pin_code_plain'] ?? null)
            ? (string) $data['pin_code_plain']
            : null;

        unset($data['pin_code_plain'], $data['pin_code_plain_confirmation']);

        $data['type'] = in_array($data['role'], ['super_admin', 'admin'], true) ? 'admin' : 'lecturer';

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

        if ($this->pendingPinCode) {
            $user->forceFill([
                'pin_code' => Hash::make($this->pendingPinCode),
                'pin_enabled' => true,
                'pin_changed_at' => now(),
            ])->saveQuietly();

            Notification::make()
                ->title(__('user.pin_reset_successfully'))
                ->success()
                ->send();
        }
    }

    protected function beforeSave(): void
    {
        $this->originalAuditAttributes = $this->getRecord()->getOriginal();
    }
}
