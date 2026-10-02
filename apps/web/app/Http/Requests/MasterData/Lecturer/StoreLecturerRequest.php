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
            'nip' => ['required', 'string', 'digits_between:8,20', Rule::unique(Lecturer::class, 'nip')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:50'],
        ];
    }
}
