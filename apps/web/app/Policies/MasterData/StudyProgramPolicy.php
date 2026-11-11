<?php

namespace App\Policies\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Permissions\Constraint\ConstraintPermissions;
use App\Permissions\MasterData\StudyProgramPermissions;
use App\Permissions\Scheduling\AllocationPermissions;
use App\Permissions\Scheduling\SchedulingPermissions;
use BackedEnum;

class StudyProgramPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function viewAny(User $user): bool
    {
        return $user->can(StudyProgramPermissions::VIEW);
    }

    public function view(User $user, StudyProgram $studyProgram): bool
    {
        return $user->can(StudyProgramPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(StudyProgramPermissions::CREATE);
    }

    public function update(User $user, StudyProgram $studyProgram): bool
    {
        return $user->can(StudyProgramPermissions::UPDATE);
    }

    public function delete(User $user, StudyProgram $studyProgram): bool
    {
        return $user->can(StudyProgramPermissions::DELETE);
    }

    public function viewAnyConstraints(User $user): bool
    {
        return $user->can(ConstraintPermissions::VIEW);
    }

    public function viewConstraints(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, ConstraintPermissions::VIEW, $studyProgram);
    }

    public function viewRoomSlots(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, AllocationPermissions::VIEW, $studyProgram);
    }

    public function requestRoomSlots(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, AllocationPermissions::REQUEST, $studyProgram);
    }

    /**
     * Where the program's schedule stands in the review flow.
     */
    public function viewSchedule(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, SchedulingPermissions::VIEW_RUN, $studyProgram);
    }

    public function submitSchedule(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, SchedulingPermissions::SUBMIT_SCHEDULE, $studyProgram);
    }

    public function reviewSchedule(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, SchedulingPermissions::REVIEW_SCHEDULE, $studyProgram);
    }

    private function canOnProgram(User $user, BackedEnum $permission, StudyProgram $studyProgram): bool
    {
        return $user->can($permission) && $this->orgScope->canAccessStudyProgram($user, $studyProgram);
    }
}
