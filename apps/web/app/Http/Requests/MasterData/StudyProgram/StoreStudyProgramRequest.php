<?php

namespace App\Http\Requests\MasterData\StudyProgram;

use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudyProgramRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Department::class, 'id')->withoutTrashed()],
            'code' => ['required', 'string', 'max:20', Rule::unique(StudyProgram::class, 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150', Rule::unique(StudyProgram::class, 'name')->withoutTrashed()],
        ];
    }
}
