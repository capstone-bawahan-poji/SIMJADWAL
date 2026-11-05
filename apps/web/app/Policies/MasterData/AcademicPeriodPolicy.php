<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\AcademicPeriod;
use App\Models\User;
use App\Permissions\MasterData\AcademicPeriodPermissions;

class AcademicPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AcademicPeriodPermissions::VIEW);
    }

    public function view(User $user, AcademicPeriod $academicPeriod): bool
    {
        return $user->can(AcademicPeriodPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(AcademicPeriodPermissions::CREATE);
    }

    public function update(User $user, AcademicPeriod $academicPeriod): bool
    {
        return $user->can(AcademicPeriodPermissions::UPDATE);
    }

    public function delete(User $user, AcademicPeriod $academicPeriod): bool
    {
        return $user->can(AcademicPeriodPermissions::DELETE);
    }
}
