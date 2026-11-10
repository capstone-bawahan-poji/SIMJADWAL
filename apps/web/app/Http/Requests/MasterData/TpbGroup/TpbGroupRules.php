<?php

namespace App\Http\Requests\MasterData\TpbGroup;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\StudyProgram;
use Illuminate\Validation\Rule;

/**
 * Shape rules shared by store and update. Rules that need the offering (class count, semester
 * parity, programs already in another group) live in TpbGroupService.
 */
final class TpbGroupRules
{
    /**
     * @param  list<string>  $presence
     * @return array<string, mixed>
     */
    public static function attributes(array $presence, ?TpbCohort $cohort): array
    {
        return [
            'cohort' => [...$presence, Rule::enum(TpbCohort::class)],
            'study_programs' => match (true) {
                $cohort === TpbCohort::PILIHAN => ['prohibited'],
                default => [...$presence, 'array', 'min:1'],
            },
            'study_programs.*.id' => ['required', 'integer', 'distinct', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'study_programs.*.target_semester' => $cohort?->isRegular()
                ? ['required', 'integer', 'between:1,8']
                : ['prohibited'],
            'class_numbers' => [...$presence, 'array', 'min:1'],
            'class_numbers.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
