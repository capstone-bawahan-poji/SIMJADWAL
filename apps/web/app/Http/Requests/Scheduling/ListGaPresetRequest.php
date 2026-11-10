<?php

namespace App\Http\Requests\Scheduling;

use Illuminate\Foundation\Http\FormRequest;

class ListGaPresetRequest extends FormRequest
{
    use ResolvesScheduleScope;

    public function rules(): array
    {
        return [
            ...$this->scheduleScopeRules(),
        ];
    }
}
