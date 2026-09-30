<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Permissions\MasterData\CoursePermissions;
use App\Permissions\MasterData\TpbPermissions;
use Illuminate\Auth\Access\Response;

/**
 * Regular courses follow their study program. TPB courses have no program: every reader
 * sees them, and only TPB permissions (admin_tpb) change them.
 */
class CoursePolicy extends StudyProgramOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(CoursePermissions::VIEW);
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can(CoursePermissions::VIEW)
            && ($course->is_tpb || $this->canRead($user, $course->studyProgram));
    }

    /**
     * Call with the owning program, or null for a TPB course:
     * Gate::authorize('create', [Course::class, $studyProgram]).
     */
    public function create(User $user, ?StudyProgram $studyProgram): Response
    {
        return $studyProgram === null
            ? $this->allowIf($user->can(TpbPermissions::CREATE))
            : $this->canWrite($user, CoursePermissions::CREATE, $studyProgram);
    }

    public function update(User $user, Course $course): Response
    {
        return $course->is_tpb
            ? $this->allowIf($user->can(TpbPermissions::UPDATE))
            : $this->canWrite($user, CoursePermissions::UPDATE, $course->studyProgram);
    }

    public function delete(User $user, Course $course): Response
    {
        return $course->is_tpb
            ? $this->allowIf($user->can(TpbPermissions::DELETE))
            : $this->canWrite($user, CoursePermissions::DELETE, $course->studyProgram);
    }
}
