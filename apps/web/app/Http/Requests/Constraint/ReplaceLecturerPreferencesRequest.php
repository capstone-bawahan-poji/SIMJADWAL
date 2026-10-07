<?php

namespace App\Http\Requests\Constraint;

use App\Models\MasterData\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole matrix of one lecturer. Slots left out are neutral.
 */
class ReplaceLecturerPreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preferences' => ['present', 'array'],
            'preferences.*.time_slot_id' => ['required', 'integer', 'distinct', Rule::exists(TimeSlot::class, 'id')],
            'preferences.*.type' => ['required', Rule::in(['want', 'avoid'])],
        ];
    }
}
