<?php

namespace App\Http\Requests\MasterData\TeachingAssignment;

use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;

class ListTeachingAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer'],
            'lecturer_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'tpb_group_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
