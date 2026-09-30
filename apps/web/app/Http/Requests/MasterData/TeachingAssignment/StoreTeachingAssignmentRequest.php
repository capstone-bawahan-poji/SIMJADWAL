<?php

namespace App\Http\Requests\MasterData\TeachingAssignment;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TpbGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules that need the course (class count, TPB group, shared room, clashes) live in TeachingAssignmentService.
 */
class StoreTeachingAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')->withoutTrashed()],
            'class_number' => [
                'required', 'integer', 'min:1',
                Rule::unique(CourseLecturer::class)->where('course_id', $this->integer('course_id')),
            ],
            'lecturer_id' => ['required', 'integer', Rule::exists(Lecturer::class, 'id')->withoutTrashed()],
            'tpb_group_id' => ['nullable', 'integer', Rule::exists(TpbGroup::class, 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists(Room::class, 'id')->withoutTrashed()],
        ];
    }
}
