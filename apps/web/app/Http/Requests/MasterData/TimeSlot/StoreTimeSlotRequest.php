<?php

namespace App\Http\Requests\MasterData\TimeSlot;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! TimeSlot::rangeMatchesType($this->input('start_time'), $this->input('end_time'), $this->integer('type'))) {
                $validator->errors()->add('end_time', __('The time range must be :minutes minutes for :sks SKS.', ['minutes' => $this->integer('type') * TimeSlot::MINUTES_PER_SKS, 'sks' => $this->integer('type')]));
            }
        }];
    }
}
