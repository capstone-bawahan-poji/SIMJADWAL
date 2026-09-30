<?php

namespace App\Http\Requests\MasterData\Lecturer;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLecturerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'study_program_id' => ['required', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'code' => ['required', 'string', 'max:10', Rule::unique(Lecturer::class, 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:50'],
        ];
    }
}
