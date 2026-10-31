<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Permissions\Scheduling\SchedulingPermissions;

/**
 * Runs are read by the faculty they schedule. superadmin passes through Gate::before.
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
        return $user->can(SchedulingPermissions::VIEW_RUN) && $this->orgScope->canAccessFaculty($user, $run->faculty_id);
    }

    public function activate(User $user, SchedulingRun $run): bool
    {
        return $user->can(SchedulingPermissions::ACTIVATE_RUN) && $this->orgScope->canAccessFaculty($user, $run->faculty_id);
    }

    public function publish(User $user, SchedulingRun $run): bool
    {
        return $user->can(SchedulingPermissions::PUBLISH_RUN) && $this->orgScope->canAccessFaculty($user, $run->faculty_id);
    }
}