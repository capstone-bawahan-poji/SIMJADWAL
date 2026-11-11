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
        return GaPresetRules::attributes($this->preset()->studyProgram, $this->preset());
    }

    public function after(): array
    {
        return [fn (Validator $validator) => GaPresetRules::validateScOverrides($validator, $this->preset()->studyProgram)];
    }

    private function preset(): GaParameterPreset
    {
        return $this->route('gaPreset');
    }
}
