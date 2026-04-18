<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(User $authUser): bool
    {
        return $authUser->can('view_any_user');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->can('view_user');
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('create_user');
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->can('update_user');
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->can('delete_user');
    }

    public function deleteAny(User $authUser): bool
    {
        return $authUser->can('delete_any_user');
    }

    public function restore(User $authUser, User $user): bool
    {
        return $authUser->can('restore_user');
    }

    public function forceDelete(User $authUser, User $user): bool
    {
        return $authUser->can('force_delete_user');
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('force_delete_any_user');
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('restore_any_user');
    }

    public function replicate(User $authUser, User $user): bool
    {
        return $authUser->can('replicate_user');
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('reorder_user');
    }

}
