<?php

namespace App\Policies\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Shared checks for data owned by one study program (lecturers, courses, teaching assignments):
 * the user must reach the program, and writes are refused with 409 CONSTRAINTS_LOCKED
 * while the program is submitted.
 */
abstract class StudyProgramOwnedPolicy
{
    public function __construct(protected readonly ResolvesOrgScope $orgScope) {}

    protected function canWrite(User $user, string|\BackedEnum $permission, StudyProgram $studyProgram): Response
    {
        if (! $user->can($permission) || ! $this->orgScope->canAccessStudyProgram($user, $studyProgram)) {
            return Response::deny();
        }

        if ($studyProgram->isLocked()) {
            return Response::denyWithStatus(409, __('Study program constraints are submitted and locked.'), 'CONSTRAINTS_LOCKED');
        }

        return Response::allow();
    }
}
