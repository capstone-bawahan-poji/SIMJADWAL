<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use App\Rules\Scheduling\GaPresetRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * faculty_id is required for a superadmin; a faculty admin always creates for their own faculty.
 */
class StoreGaPresetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => [
                Rule::requiredIf(fn () => $this->user()->role === Role::SUPER_ADMIN),
                'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed(),
            ],
            ...GaPresetRules::attributes($this->facultyId()),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => GaPresetRules::validateScOverrides($validator, $this->facultyId())];
    }

    public function facultyId(): ?int
    {
        if ($this->user()->role !== Role::SUPER_ADMIN) {
            return $this->user()->faculty_id;
        }

        return is_numeric($this->input('faculty_id')) ? (int) $this->input('faculty_id') : null;
    }
}
