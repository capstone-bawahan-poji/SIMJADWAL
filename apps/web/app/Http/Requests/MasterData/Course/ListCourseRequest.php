<?php

namespace App\Http\Requests\MasterData\Course;

use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;

class ListCourseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'is_tpb' => ['nullable', 'boolean'],
            'faculty_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'between:1,8'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
