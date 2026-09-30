<?php

namespace App\Http\Requests\MasterData\Lecturer;

use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;

class ListLecturerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'faculty_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
