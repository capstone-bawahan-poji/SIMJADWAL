<?php

namespace App\Policies\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Permissions\Constraint\ConstraintPermissions;
use App\Permissions\MasterData\StudyProgramPermissions;

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

    public function submitConstraints(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, ConstraintPermissions::SUBMIT, $studyProgram);
    }

    public function reviewConstraints(User $user, StudyProgram $studyProgram): bool
    {
        return $this->canOnProgram($user, ConstraintPermissions::REVIEW, $studyProgram);
    }

    private function canOnProgram(User $user, ConstraintPermissions $permission, StudyProgram $studyProgram): bool
    {
        return $user->can($permission) && $this->orgScope->canAccessStudyProgram($user, $studyProgram);
    }
}
