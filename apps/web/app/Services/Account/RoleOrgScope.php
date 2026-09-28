<?php

namespace App\Services\Account;

use App\Contracts\Account\ResolvesOrgScope;
use App\Enums\Account\Role;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

readonly class RoleOrgScope implements ResolvesOrgScope
{
    public function facultyId(User $user): ?int
    {
        return match ($user->role) {
            Role::FACULTY_ADMIN => $user->faculty_id,
            Role::STUDY_PROGRAM_ADMIN, Role::STUDENT => $user->studyProgram?->faculty_id,
            Role::LECTURER => $user->lecturer?->studyProgram?->faculty_id,
            default => null,
        };
    }

    public function studyProgramId(User $user): ?int
    {
        return match ($user->role) {
            Role::STUDY_PROGRAM_ADMIN, Role::STUDENT => $user->study_program_id,
            Role::LECTURER => $user->lecturer?->study_program_id,
            default => null,
        };
    }

    public function canAccessFaculty(User $user, ?int $facultyId): bool
    {
        if ($user->role === Role::SUPER_ADMIN) {
            return true;
        }

        $ownFacultyId = $this->facultyId($user);

        return $ownFacultyId !== null && $ownFacultyId === $facultyId;
    }

    public function canAccessStudyProgram(User $user, StudyProgram $studyProgram): bool
    {
        return match ($user->role) {
            Role::SUPER_ADMIN => true,
            Role::FACULTY_ADMIN => $user->faculty_id === $studyProgram->faculty_id,
            null => false,
            default => $this->studyProgramId($user) === $studyProgram->id,
        };
    }

    public function applyFacultyScope(Builder $query, User $user, string $column = 'faculty_id'): Builder
    {
        if ($user->role === Role::SUPER_ADMIN) {
            return $query;
        }

        return $query->where($query->qualifyColumn($column), $this->facultyId($user));
    }

    public function applyStudyProgramScope(Builder $query, User $user, string $column = 'study_program_id'): Builder
    {
        return match ($user->role) {
            Role::SUPER_ADMIN => $query,
            Role::FACULTY_ADMIN => $query->whereIn(
                $query->qualifyColumn($column),
                StudyProgram::query()->select('id')->where('faculty_id', $user->faculty_id),
            ),
            default => $query->where($query->qualifyColumn($column), $this->studyProgramId($user)),
        };
    }
}
