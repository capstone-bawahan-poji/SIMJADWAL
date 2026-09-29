<?php

namespace App\Rules\Account;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
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
            'faculty_id' => $role?->requiresFaculty()
                ? [...$presence, 'integer', Rule::exists(Faculty::class, 'id')]
                : ['prohibited'],
            'study_program_id' => $role?->requiresStudyProgram()
                ? [...$presence, 'integer', Rule::exists(StudyProgram::class, 'id')]
                : ['prohibited'],
            'lecturer_id' => $role?->requiresLecturer()
                ? [...$presence, 'integer', Rule::exists(Lecturer::class, 'id')->where(
                    fn (Builder $query) => $query->whereNull('user_id')->when(
                        $ignoreUserId,
                        fn (Builder $query) => $query->orWhere('user_id', $ignoreUserId),
                    ),
                )]
                : ['prohibited'],
        ];
    }
}
