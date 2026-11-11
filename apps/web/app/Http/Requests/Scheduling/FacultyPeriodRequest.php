<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

class FacultyPeriodRequest extends FormRequest
{
    use ResolvesFacultyScope;

    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            ...$this->facultyScopeRules(),
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }
}
