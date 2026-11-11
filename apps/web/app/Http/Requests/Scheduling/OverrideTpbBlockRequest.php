<?php

namespace App\Http\Requests\Scheduling;

use App\Models\MasterData\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A TPB block moves by slot only: the service picks a room for every class of the group.
 */
class OverrideTpbBlockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'time_slot_id' => ['required', 'integer', Rule::exists(TimeSlot::class, 'id')],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
