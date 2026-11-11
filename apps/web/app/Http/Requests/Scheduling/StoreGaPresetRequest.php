<?php

namespace App\Http\Requests\Scheduling;

use App\Models\Scheduling\GaParameterPreset;
use App\Rules\Scheduling\GaPresetRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * study_program_id (or scope=tpb) is required for a superadmin; other admins create for their own scope.
 */
class StoreGaPresetRequest extends FormRequest
{
    use ResolvesScheduleScope;

    /**
     * Before validation, so a program outside the user's reach gets 403, not name errors.
     * An unknown program id passes here and fails study_program_id validation.
     */
    public function authorize(): bool
    {
        $program = $this->studyProgramOrNull();

        return $program === null && $this->studyProgramId() !== null
            || $this->user()->can('create', [GaParameterPreset::class, $program]);
    }

    public function rules(): array
    {
        return [
            ...$this->scheduleScopeRules(),
            ...GaPresetRules::attributes($this->studyProgramOrNull()),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => GaPresetRules::validateScOverrides($validator, $this->studyProgramOrNull())];
    }
}
