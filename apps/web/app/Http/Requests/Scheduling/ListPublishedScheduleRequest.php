<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Integration API. The token needs the schedule:read ability (a login token has every ability).
 */
class ListPublishedScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->tokenCan('schedule:read');
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'faculty_code' => ['nullable', 'string', 'max:20'],
            'study_program_code' => ['nullable', 'string', 'max:20'],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'format' => ['nullable', Rule::in(['csv', 'json'])],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }

    /**
     * @return array{faculty_code: string|null, study_program_code: string|null, updated_since: string|null}
     */
    public function filters(): array
    {
        return [
            'faculty_code' => $this->validated('faculty_code'),
            'study_program_code' => $this->validated('study_program_code'),
            'updated_since' => $this->validated('updated_since'),
        ];
    }
}
