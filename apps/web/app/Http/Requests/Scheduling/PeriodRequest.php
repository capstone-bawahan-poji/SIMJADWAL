<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only a period, defaulting to the current one. The controller authorizes.
 */
class PeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }
}
