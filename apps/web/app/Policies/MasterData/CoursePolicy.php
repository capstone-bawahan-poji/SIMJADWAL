<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Permissions\MasterData\CoursePermissions;
use Illuminate\Auth\Access\Response;

class CoursePolicy extends StudyProgramOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(CoursePermissions::VIEW);
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can(CoursePermissions::VIEW)
            && $this->orgScope->canAccessStudyProgram($user, $course->studyProgram);
    }

    /**
     * Call with the owning program: Gate::authorize('create', [Course::class, $studyProgram]).
     */
    public function create(User $user, StudyProgram $studyProgram): Response
    {
        return $this->canWrite($user, CoursePermissions::CREATE, $studyProgram);
    }

    public function update(User $user, Course $course): Response
    {
        return $this->canWrite($user, CoursePermissions::UPDATE, $course->studyProgram);
    }

    public function delete(User $user, Course $course): Response
    {
        return $this->canWrite($user, CoursePermissions::DELETE, $course->studyProgram);
    }
}
