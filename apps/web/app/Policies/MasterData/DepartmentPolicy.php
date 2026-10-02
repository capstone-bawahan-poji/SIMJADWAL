<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Department;
use App\Models\User;
use App\Permissions\MasterData\DepartmentPermissions;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(DepartmentPermissions::VIEW);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can(DepartmentPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(DepartmentPermissions::CREATE);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can(DepartmentPermissions::UPDATE);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can(DepartmentPermissions::DELETE);
    }
}
