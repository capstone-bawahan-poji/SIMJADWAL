<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\TpbOffering;
use App\Models\User;
use App\Permissions\MasterData\TpbPermissions;

/**
 * TPB offerings are institute-wide: readable by every admin, managed by admin_tpb.
 */
class TpbOfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TpbPermissions::VIEW);
    }

    public function view(User $user, TpbOffering $tpbOffering): bool
    {
        return $user->can(TpbPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(TpbPermissions::CREATE);
    }

    public function update(User $user, TpbOffering $tpbOffering): bool
    {
        return $user->can(TpbPermissions::UPDATE);
    }

    public function delete(User $user, TpbOffering $tpbOffering): bool
    {
        return $user->can(TpbPermissions::DELETE);
    }
}
