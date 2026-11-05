<?php

namespace App\Http\Requests\Scheduling;

use App\Models\Scheduling\GaParameterPreset;
use App\Rules\Scheduling\GaPresetRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGaPresetRequest extends FormRequest
{
    public function rules(): array
    {
        return GaPresetRules::attributes($this->preset()->faculty_id, $this->preset());
    }

    public function after(): array
    {
        return [fn (Validator $validator) => GaPresetRules::validateScOverrides($validator, $this->preset()->faculty_id)];
    }

    private function preset(): GaParameterPreset
    {
        return $this->route('gaPreset');
    }
}
