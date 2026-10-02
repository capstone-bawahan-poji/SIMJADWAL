<?php

namespace App\Http\Requests\MasterData\TpbGroup;

use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clash rules (a program in two groups of one slot) are checked in TpbGroupService.
 */
class StoreTpbGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')->withoutTrashed()->where('is_tpb', true)],
            'code' => ['required', 'string', 'max:20', Rule::unique(TpbGroup::class)->where('course_id', $this->integer('course_id'))],
            'time_slot_id' => ['nullable', 'integer', Rule::exists(TimeSlot::class, 'id')],
            'study_program_ids' => ['required', 'array', 'min:1'],
            'study_program_ids.*' => ['integer', 'distinct', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
        ];
    }
}
