<?php

namespace App\Policies\Account;

use App\Models\User;
use App\Permissions\Account\ActivityLogPermissions;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ActivityLogPermissions::VIEW);
    }
}
