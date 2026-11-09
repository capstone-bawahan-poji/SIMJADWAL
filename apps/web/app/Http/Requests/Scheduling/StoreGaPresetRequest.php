<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\GaPresetRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * faculty_id (or scope=tpb) is required for a superadmin; other admins create for their own scope.
 */
class StoreGaPresetRequest extends FormRequest
{
    use ResolvesScheduleScope;

    public function rules(): array
    {
        return [
            ...$this->scheduleScopeRules(),
            ...GaPresetRules::attributes($this->facultyId()),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => GaPresetRules::validateScOverrides($validator, $this->facultyId())];
    }
}
