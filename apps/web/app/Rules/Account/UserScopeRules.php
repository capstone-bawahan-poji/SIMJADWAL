<?php

namespace App\Rules\Account;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

final class UserScopeRules
{
    /**
     * @param  bool  $partial  true on PATCH without a role change: present fields are validated, absent ones are kept
     * @return array<string, array<int, mixed>>
     */
    public static function for(?Role $role, bool $partial = false, ?int $ignoreUserId = null): array
    {
        $presence = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'identity_number' => $role?->requiresLecturer()
                ? ['prohibited']
                : ['sometimes', 'nullable', 'string', 'digits_between:8,20', Rule::unique(User::class, 'identity_number')->ignore($ignoreUserId)],
            'faculty_id' => $role?->requiresFaculty()
                ? [...$presence, 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()]
                : ['prohibited'],
            'study_program_id' => $role?->requiresStudyProgram()
                ? [...$presence, 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()]
                : ['prohibited'],
            'lecturer_id' => $role?->requiresLecturer()
                ? [...$presence, 'integer', Rule::exists(Lecturer::class, 'id')->withoutTrashed()->where(
                    fn (Builder $query) => $query->whereNull('user_id')->when(
                        $ignoreUserId,
                        fn (Builder $query) => $query->orWhere('user_id', $ignoreUserId),
                    ),
                )]
                : ['prohibited'],
        ];
    }
}
