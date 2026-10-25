<?php

namespace App\Http\Requests\MasterData\Course;

use App\Models\MasterData\StudyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportCourseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'study_program_id' => ['required', 'integer', Rule::exists(StudyProgram::class, 'id')->withoutTrashed()],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:2048'],
            'dry_run' => ['required', 'boolean'],
            'resolutions' => ['nullable', 'json'],
            'plan_hash' => ['required_if_declined:dry_run', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function resolutions(): array
    {
        $decoded = json_decode((string) $this->input('resolutions', '{}'), true);

        return collect(is_array($decoded) ? $decoded : [])
            ->filter(fn (mixed $id, int|string $key) => is_string($key) && is_int($id))
            ->all();
    }
}