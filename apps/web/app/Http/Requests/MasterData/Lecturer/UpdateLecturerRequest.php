<?php

namespace App\Http\Requests\MasterData\Lecturer;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional, absent fields keep their value.
 */
class UpdateLecturerRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Lecturer $lecturer */
        $lecturer = $this->route('lecturer');

        return [
            'study_program_id' => ['sometimes', 'required', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'code' => ['sometimes', 'required', 'string', 'max:10', Rule::unique(Lecturer::class, 'code')->withoutTrashed()->ignore($lecturer)],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'title' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
