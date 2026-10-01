<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Permissions\MasterData\StudyProgramPermissions;

class StudyProgramPolicy
{
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
}
