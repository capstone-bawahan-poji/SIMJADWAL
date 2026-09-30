<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\User;
use App\Permissions\MasterData\CourseLecturerPermissions;
use App\Permissions\MasterData\TpbPermissions;
use Illuminate\Auth\Access\Response;

/**
 * A teaching assignment follows its course: the course's study program for regular
 * courses, TPB permissions for TPB classes.
 */
class CourseLecturerPolicy extends StudyProgramOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(CourseLecturerPermissions::VIEW);
    }

    public function view(User $user, CourseLecturer $courseLecturer): bool
    {
        $course = $courseLecturer->course;

        return $user->can(CourseLecturerPermissions::VIEW)
            && ($course->is_tpb || $this->canRead($user, $course->studyProgram));
    }

    /**
     * Call with the course: Gate::authorize('create', [CourseLecturer::class, $course]).
     */
    public function create(User $user, Course $course): Response
    {
        return $this->write($user, $course, TpbPermissions::CREATE, CourseLecturerPermissions::CREATE);
    }

    public function update(User $user, CourseLecturer $courseLecturer): Response
    {
        return $this->write($user, $courseLecturer->course, TpbPermissions::UPDATE, CourseLecturerPermissions::UPDATE);
    }

    public function delete(User $user, CourseLecturer $courseLecturer): Response
    {
        return $this->write($user, $courseLecturer->course, TpbPermissions::DELETE, CourseLecturerPermissions::DELETE);
    }

    private function write(User $user, Course $course, TpbPermissions $tpb, CourseLecturerPermissions $regular): Response
    {
        return $course->is_tpb
            ? $this->allowIf($user->can($tpb))
            : $this->canWrite($user, $regular, $course->studyProgram);
    }
}
