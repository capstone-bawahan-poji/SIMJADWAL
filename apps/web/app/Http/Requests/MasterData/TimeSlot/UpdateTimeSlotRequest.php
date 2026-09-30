<?php

namespace App\Http\Requests\MasterData\TimeSlot;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH: every field is optional. Uniqueness and time order are checked on the merged
 * values, so sending only end_time still compares it with the stored start_time.
 */
class UpdateTimeSlotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'day' => ['sometimes', 'required', Rule::enum(Day::class)],
            'session' => ['sometimes', 'required', 'integer', 'between:1,10'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'type' => ['sometimes', 'required', 'integer', 'in:2,3'],
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

            /** @var TimeSlot $timeSlot */
            $timeSlot = $this->route('timeSlot');
            $day = $this->filled('day') ? $this->integer('day') : $timeSlot->day->value;
            $session = $this->filled('session') ? $this->integer('session') : $timeSlot->session;

            $taken = TimeSlot::query()->whereKeyNot($timeSlot->id)->where('day', $day)->where('session', $session)->exists();
            if ($taken) {
                $validator->errors()->add('session', __('Another time slot already uses this day and session.'));
            }

            if ($this->input('end_time', $timeSlot->end_time) <= $this->input('start_time', $timeSlot->start_time)) {
                $validator->errors()->add('end_time', __('The end time must be after the start time.'));
            }
        }];
    }
}
