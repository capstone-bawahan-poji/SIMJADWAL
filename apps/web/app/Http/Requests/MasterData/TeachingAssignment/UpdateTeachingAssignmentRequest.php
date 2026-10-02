<?php

namespace App\Http\Requests\MasterData\TeachingAssignment;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TpbGroup;
use App\Rules\MasterData\Unchanged;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. The course cannot change; delete and create instead.
 */
class UpdateTeachingAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var CourseLecturer $assignment */
        $assignment = $this->route('courseLecturer');

        return [
            'course_id' => ['sometimes', 'integer', new Unchanged($assignment->course_id)],
            'class_number' => [
                'sometimes', 'required', 'integer', 'min:1',
                Rule::unique(CourseLecturer::class)->where('course_id', $assignment->course_id)->ignore($assignment),
            ],
            'lecturer_id' => ['sometimes', 'required', 'integer', Rule::exists(Lecturer::class, 'id')->withoutTrashed()],
            'tpb_group_id' => ['sometimes', 'nullable', 'integer', Rule::exists(TpbGroup::class, 'id')],
            'room_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Room::class, 'id')->withoutTrashed()],
        ];
    }
}
