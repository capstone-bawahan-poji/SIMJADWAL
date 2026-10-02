<?php

namespace App\Http\Requests\MasterData\StudyProgram;

use Illuminate\Foundation\Http\FormRequest;

class ListStudyProgramRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'faculty_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ];
    }
}
