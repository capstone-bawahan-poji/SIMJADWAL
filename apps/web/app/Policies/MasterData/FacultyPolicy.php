<?php

namespace App\Policies\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\Faculty;
use App\Models\User;
use App\Permissions\MasterData\FacultyPermissions;
use App\Permissions\Scheduling\AllocationPermissions;
use App\Permissions\Scheduling\SchedulingPermissions;

/**
 * Master data abilities, plus the faculty side of scheduling (POS 040/IT10.1/OT.07/2026): handing out
 * room slots, importing the TPB schedule, reviewing and publishing the programs' schedules.
 * Withdrawing a publication is left to superadmin (Gate::before).
 */
class FacultyPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

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

    /**
     * Read access: admins of the faculty and its programs, plus those who read every faculty.
     */
    public function viewRoomSlots(User $user, Faculty $faculty): bool
    {
        return $user->can(AllocationPermissions::VIEW)
            && ($this->orgScope->readsAllFaculties($user) || $this->orgScope->facultyId($user) === $faculty->id);
    }

    public function manageRoomSlots(User $user, Faculty $faculty): bool
    {
        return $user->can(AllocationPermissions::MANAGE) && $this->orgScope->canAccessFaculty($user, $faculty->id);
    }

    public function importTpbSchedule(User $user, Faculty $faculty): bool
    {
        return $user->can(SchedulingPermissions::IMPORT_TPB) && $this->orgScope->canAccessFaculty($user, $faculty->id);
    }

    public function viewSchedules(User $user, Faculty $faculty): bool
    {
        return $user->can(SchedulingPermissions::VIEW_RUN) && $this->orgScope->canAccessFaculty($user, $faculty->id);
    }

    public function reviewSchedules(User $user, Faculty $faculty): bool
    {
        return $user->can(SchedulingPermissions::REVIEW_SCHEDULE) && $this->orgScope->canAccessFaculty($user, $faculty->id);
    }

    public function publishSchedule(User $user, Faculty $faculty): bool
    {
        return $user->can(SchedulingPermissions::PUBLISH_RUN) && $this->reviewSchedules($user, $faculty);
    }

    public function unpublishSchedule(User $user, Faculty $faculty): bool
    {
        return false;
    }
}
