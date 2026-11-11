<?php

namespace App\Http\Requests\Scheduling;

use Illuminate\Foundation\Http\FormRequest;

class FulfillSlotRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cells' => ['required', 'array', 'min:1', 'max:500'],
            'cells.*.time_slot_id' => ['required', 'integer'],
            'cells.*.room_id' => ['required', 'integer'],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array{time_slot_id: int, room_id: int}>
     */
    public function cells(): array
    {
        return array_map(fn (array $cell) => [
            'time_slot_id' => (int) $cell['time_slot_id'],
            'room_id' => (int) $cell['room_id'],
        ], array_values($this->validated('cells')));
    }
}
