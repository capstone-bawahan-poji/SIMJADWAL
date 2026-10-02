<?php

namespace App\Http\Requests\MasterData\TimeSlot;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeSlotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'day' => ['required', Rule::enum(Day::class)],
            'session' => ['required', 'integer', 'between:1,10', Rule::unique(TimeSlot::class)->where('day', $this->integer('day'))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', 'integer', 'in:2,3'],
        ];
    }
}
