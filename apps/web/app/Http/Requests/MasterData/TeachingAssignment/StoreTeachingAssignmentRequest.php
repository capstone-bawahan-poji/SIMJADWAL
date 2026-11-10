<?php

namespace App\Http\Requests\MasterData\TeachingAssignment;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regular courses only: TPB classes are created by their group (TpbGroupService).
 * Rules that need the course (class count, TPB course) live in TeachingAssignmentService.
 */
class StoreTeachingAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')->withoutTrashed()],
            'class_number' => [
                'required', 'integer', 'min:1',
                Rule::unique(CourseLecturer::class)->where('course_id', $this->integer('course_id'))->whereNull('tpb_offering_id'),
            ],
            'lecturer_id' => ['required', 'integer', Rule::exists(Lecturer::class, 'id')->withoutTrashed()],
        ];
    }
}
