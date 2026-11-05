<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Permissions\Scheduling\SchedulingPermissions;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowSchedulingDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(SchedulingPermissions::VIEW_DATASET);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'faculty_id' => [
                Rule::requiredIf(fn () => $this->user()->role === Role::SUPER_ADMIN),
                'nullable', 'integer', Rule::exists('faculties', 'id')->withoutTrashed(),
            ],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }

    public function facultyId(): int
    {
        return $this->user()->role === Role::SUPER_ADMIN
            ? (int) $this->validated('faculty_id')
            : (int) $this->user()->faculty_id;
    }
}
