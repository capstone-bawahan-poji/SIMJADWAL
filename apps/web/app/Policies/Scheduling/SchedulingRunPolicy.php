<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Permissions\Scheduling\SchedulingPermissions;

/**
 * Program runs are run and activated by their program, and read by its faculty. TPB runs
 * (faculty null) belong to admin_tpb. superadmin passes through Gate::before.
 */
class SchedulingRunPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function viewAny(User $user): bool
    {
        return $user->can(SchedulingPermissions::VIEW_RUN);
    }

    public function view(User $user, SchedulingRun $run): bool
    {
        return $user->can(SchedulingPermissions::VIEW_RUN) && $this->canAccess($user, $run);
    }

    public function activate(User $user, SchedulingRun $run): bool
    {
        return $user->can(SchedulingPermissions::ACTIVATE_RUN) && $this->canAccess($user, $run);
    }

    /**
     * TPB runs only; SchedulingRunActivationService explains why a program run cannot be published alone.
     */
    public function publish(User $user, SchedulingRun $run): bool
    {
        return $user->can(SchedulingPermissions::PUBLISH_RUN) && $this->canAccess($user, $run);
    }

    private function canAccess(User $user, SchedulingRun $run): bool
    {
        return $this->orgScope->canAccessScheduleScope($user, $run->faculty_id, $run->study_program_id);
    }
}
