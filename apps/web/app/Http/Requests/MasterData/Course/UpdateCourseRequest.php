<?php

namespace App\Http\Requests\MasterData\Course;

use App\Models\MasterData\Course;
use App\Rules\MasterData\CourseRules;
use App\Rules\MasterData\Unchanged;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. The owner (study_program_id, is_tpb) cannot change.
 */
class UpdateCourseRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');

        return [
            'is_tpb' => ['sometimes', 'boolean', new Unchanged($course->is_tpb)],
            'study_program_id' => ['sometimes', 'nullable', 'integer', new Unchanged($course->study_program_id)],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(Course::class, 'code')->withoutTrashed()->ignore($course)],
            ...CourseRules::attributes(presence: ['sometimes', 'required']),
        ];
    }
}
