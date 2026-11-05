<?php

namespace App\Http\Requests\MasterData\Course;

use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseImportTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'study_program_id' => ['required', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'prefill' => ['nullable', 'boolean'],
        ];
    }
}