<?php

namespace App\Http\Requests\MasterData\StudyProgram;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. constraint_status is changed by Module 3, not here.
 */
class UpdateStudyProgramRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var StudyProgram $studyProgram */
        $studyProgram = $this->route('studyProgram');

        return [
            'faculty_id' => ['sometimes', 'required', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(StudyProgram::class, 'code')->withoutTrashed()->ignore($studyProgram)],
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique(StudyProgram::class, 'name')->withoutTrashed()->ignore($studyProgram)],
        ];
    }
}
