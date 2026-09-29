<?php

namespace App\Contracts\Account;

use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides which faculty and study program data a user may reach.
 * Policies and services ask this contract instead of checking roles directly.
 */
interface ResolvesOrgScope
{
    /**
     * Faculty the user is locked to. Null = unrestricted.
     */
    public function facultyId(User $user): ?int;

    /**
     * Study program the user is locked to. Null = not locked to one program.
     */
    public function studyProgramId(User $user): ?int;

    public function canAccessFaculty(User $user, ?int $facultyId): bool;

    public function canAccessStudyProgram(User $user, StudyProgram $studyProgram): bool;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyFacultyScope(Builder $query, User $user, string $column = 'faculty_id'): Builder;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyStudyProgramScope(Builder $query, User $user, string $column = 'study_program_id'): Builder;
}
