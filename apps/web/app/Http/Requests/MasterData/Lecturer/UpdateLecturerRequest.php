<?php

namespace App\Http\Requests\MasterData\Lecturer;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLecturerRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Lecturer $lecturer */
        $lecturer = $this->route('lecturer');

        return [
            'study_program_id' => ['sometimes', 'required', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'nip' => ['sometimes', 'required', 'string', 'digits_between:8,20', Rule::unique(Lecturer::class, 'nip')->withoutTrashed()->ignore($lecturer)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'title' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
