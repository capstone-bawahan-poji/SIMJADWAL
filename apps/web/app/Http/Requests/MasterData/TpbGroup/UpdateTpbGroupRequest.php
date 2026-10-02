<?php

namespace App\Http\Requests\MasterData\TpbGroup;

use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Rules\MasterData\Unchanged;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. study_program_ids replaces the whole participant list.
 */
class UpdateTpbGroupRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var TpbGroup $group */
        $group = $this->route('tpbGroup');

        return [
            'course_id' => ['sometimes', 'integer', new Unchanged($group->course_id)],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(TpbGroup::class)->where('course_id', $group->course_id)->ignore($group)],
            'time_slot_id' => ['sometimes', 'nullable', 'integer', Rule::exists(TimeSlot::class, 'id')],
            'study_program_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'study_program_ids.*' => ['integer', 'distinct', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
        ];
    }
}
