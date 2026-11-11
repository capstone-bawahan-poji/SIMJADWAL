<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Rules\Scheduling\PeriodRule;
use App\Services\Scheduling\Export\ScheduleExportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportRoomGridRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::SUPER_ADMIN->value, Role::TPB_ADMIN->value, Role::FACULTY_ADMIN->value]);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'scope' => ['nullable', Rule::in(['faculty', 'study_program', 'tpb'])],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            ...$this->formatRules(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function formatRules(): array
    {
        return ['format' => ['required', Rule::in(array_keys(ScheduleExportService::ROOM_GRID_FORMATS))]];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }
}
