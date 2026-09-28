<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\User;
use App\Permissions\MasterData\CourseLecturerPermissions;
use Illuminate\Auth\Access\Response;

/**
 * A teaching assignment belongs to the study program of its course.
 */
class CourseLecturerPolicy extends StudyProgramOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(CourseLecturerPermissions::VIEW);
    }

    public function view(User $user, CourseLecturer $courseLecturer): bool
    {
        return $user->can(CourseLecturerPermissions::VIEW)
            && $this->orgScope->canAccessStudyProgram($user, $courseLecturer->course->studyProgram);
    }

    /**
     * Call with the course: Gate::authorize('create', [CourseLecturer::class, $course]).
     */
    public function create(User $user, Course $course): Response
    {
        return $this->canWrite($user, CourseLecturerPermissions::CREATE, $course->studyProgram);
    }

    public function update(User $user, CourseLecturer $courseLecturer): Response
    {
        return $this->canWrite($user, CourseLecturerPermissions::UPDATE, $courseLecturer->course->studyProgram);
    }

    public function delete(User $user, CourseLecturer $courseLecturer): Response
    {
        return $this->canWrite($user, CourseLecturerPermissions::DELETE, $courseLecturer->course->studyProgram);
    }
}
