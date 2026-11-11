<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\User;
use App\Permissions\Scheduling\SchedulingPermissions;

/**
 * A study program admin moves the meetings of their own program's run. admin_tpb moves the
 * meetings of the TPB run (TpbOverrideService refuses it once published). superadmin passes
 * through Gate::before.
 */
class ScheduleDetailPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function override(User $user, ScheduleDetail $detail): bool
    {
        $run = $detail->schedulingRun;

        return $user->can(SchedulingPermissions::OVERRIDE_SCHEDULE)
            && ($run->isTpb() || $run->study_program_id !== null)
            && $this->orgScope->canAccessScheduleScope($user, $run->faculty_id, $run->study_program_id);
    }
}
