<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\SlotRequest;
use App\Models\User;
use App\Permissions\Scheduling\AllocationPermissions;

/**
 * A study program admin asks for and cancels the requests of their program; the faculty admin
 * answers the requests of its programs. superadmin passes through Gate::before.
 */
class SlotRequestPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function viewAny(User $user): bool
    {
        return $user->can(AllocationPermissions::VIEW);
    }

    public function view(User $user, SlotRequest $request): bool
    {
        return $user->can(AllocationPermissions::VIEW) && $this->orgScope->canAccessStudyProgram($user, $request->studyProgram);
    }

    /**
     * Gate::authorize('create', [SlotRequest::class, $studyProgram]).
     */
    public function create(User $user, StudyProgram $studyProgram): bool
    {
        return $user->can(AllocationPermissions::REQUEST) && $this->orgScope->canAccessStudyProgram($user, $studyProgram);
    }

    public function review(User $user, SlotRequest $request): bool
    {
        return $user->can(AllocationPermissions::REVIEW_REQUEST) && $this->orgScope->canAccessFaculty($user, $request->studyProgram->faculty_id);
    }

    public function cancel(User $user, SlotRequest $request): bool
    {
        return $user->can(AllocationPermissions::REQUEST) && $this->orgScope->canAccessStudyProgram($user, $request->studyProgram);
    }
}
