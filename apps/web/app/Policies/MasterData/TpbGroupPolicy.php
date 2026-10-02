<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\TpbGroup;
use App\Models\User;
use App\Permissions\MasterData\TpbPermissions;

/**
 * TPB groups are institute-wide: readable by every admin, managed by admin_tpb.
 */
class TpbGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TpbPermissions::VIEW);
    }

    public function view(User $user, TpbGroup $tpbGroup): bool
    {
        return $user->can(TpbPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(TpbPermissions::CREATE);
    }

    public function update(User $user, TpbGroup $tpbGroup): bool
    {
        return $user->can(TpbPermissions::UPDATE);
    }

    public function delete(User $user, TpbGroup $tpbGroup): bool
    {
        return $user->can(TpbPermissions::DELETE);
    }
}
