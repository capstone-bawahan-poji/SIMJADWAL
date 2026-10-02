<?php

namespace App\Http\Requests\MasterData\Course;

use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Rules\MasterData\CourseRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A regular course needs study_program_id. A TPB course (is_tpb = true) has none.
 */
class StoreCourseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_tpb' => ['sometimes', 'boolean'],
            'study_program_id' => $this->boolean('is_tpb')
                ? ['prohibited']
                : ['required', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'code' => ['required', 'string', 'max:20', Rule::unique(Course::class, 'code')->withoutTrashed()],
            ...CourseRules::attributes(presence: ['required']),
        ];
    }
}
