<?php

namespace App\Http\Requests\Scheduling;

use App\Permissions\Scheduling\SchedulingPermissions;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowSchedulingDatasetRequest extends FormRequest
{
    use ResolvesScheduleScope;

    public function authorize(): bool
    {
        return $this->user()->can(SchedulingPermissions::VIEW_DATASET);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'revise_group_ids' => ['nullable', 'array'],
            'revise_group_ids.*' => ['integer'],
            ...$this->scheduleScopeRules(),
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }
}
