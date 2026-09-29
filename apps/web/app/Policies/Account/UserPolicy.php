<?php

namespace App\Policies\Account;

use App\Models\User;
use App\Permissions\Account\UserPermissions;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(UserPermissions::VIEW);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(UserPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(UserPermissions::CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(UserPermissions::UPDATE);
    }

    public function updateStatus(User $user, User $model): bool
    {
        return $user->can(UserPermissions::UPDATE_STATUS);
    }
}
