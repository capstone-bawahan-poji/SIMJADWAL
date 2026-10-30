<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\User;
use App\Permissions\Scheduling\SchedulingPermissions;

/**
 * A faculty admin overrides genes of their own faculty's runs. superadmin passes through Gate::before.
 */
class ScheduleDetailPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function override(User $user, ScheduleDetail $detail): bool
    {
        return $user->can(SchedulingPermissions::OVERRIDE_SCHEDULE)
            && $this->orgScope->canAccessFaculty($user, $detail->schedulingRun->faculty_id);
    }
}