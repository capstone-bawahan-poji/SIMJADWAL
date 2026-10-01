<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Faculty;
use App\Models\User;
use App\Permissions\MasterData\FacultyPermissions;

class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FacultyPermissions::VIEW);
    }

    public function view(User $user, Faculty $faculty): bool
    {
        return $user->can(FacultyPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(FacultyPermissions::CREATE);
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $user->can(FacultyPermissions::UPDATE);
    }

    public function delete(User $user, Faculty $faculty): bool
    {
        return $user->can(FacultyPermissions::DELETE);
    }
}
