<?php

namespace App\Http\Requests\MasterData\TeachingAssignment;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Rules\MasterData\Unchanged;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. The course cannot change; delete and create instead.
 * A TPB class only changes its lecturer (null clears it): its number and group follow TpbGroupService.
 */
class UpdateTeachingAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var CourseLecturer $assignment */
        $assignment = $this->route('courseLecturer');

        return [
            'course_id' => ['sometimes', 'integer', new Unchanged($assignment->course_id)],
            'class_number' => $assignment->tpb_offering_id !== null
                ? ['sometimes', 'integer', new Unchanged($assignment->class_number)]
                : [
                    'sometimes', 'required', 'integer', 'min:1',
                    Rule::unique(CourseLecturer::class)->where('course_id', $assignment->course_id)->whereNull('tpb_offering_id')->ignore($assignment),
                ],
            'lecturer_id' => [
                'sometimes', $assignment->tpb_offering_id !== null ? 'nullable' : 'required',
                'integer', Rule::exists(Lecturer::class, 'id')->withoutTrashed(),
            ],
        ];
    }
}
