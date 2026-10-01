<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Permissions\MasterData\LecturerPermissions;
use Illuminate\Auth\Access\Response;

/**
 * Lecturers are visible faculty-wide (teaching assignments may pick any lecturer in the faculty),
 * but only the homebase study program may change them.
 */
class LecturerPolicy extends StudyProgramOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LecturerPermissions::VIEW);
    }

    public function view(User $user, Lecturer $lecturer): bool
    {
        return $user->can(LecturerPermissions::VIEW)
            && $this->orgScope->canAccessFaculty($user, $lecturer->studyProgram->faculty_id);
    }

    /**
     * Call with the homebase program: Gate::authorize('create', [Lecturer::class, $studyProgram]).
     */
    public function create(User $user, StudyProgram $studyProgram): Response
    {
        return $this->canWrite($user, LecturerPermissions::CREATE, $studyProgram);
    }

    public function update(User $user, Lecturer $lecturer): Response
    {
        return $this->canWrite($user, LecturerPermissions::UPDATE, $lecturer->studyProgram);
    }

    public function delete(User $user, Lecturer $lecturer): Response
    {
        return $this->canWrite($user, LecturerPermissions::DELETE, $lecturer->studyProgram);
    }
}
