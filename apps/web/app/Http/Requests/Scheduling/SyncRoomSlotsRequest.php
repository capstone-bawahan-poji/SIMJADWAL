<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * cells: the cells whose holder changes; study_program_id null takes the cell back.
 */
class SyncRoomSlotsRequest extends FormRequest
{
    use ResolvesFacultyScope;

    public function rules(): array
    {
        return [
            'period' => ['required', new PeriodRule],
            ...$this->facultyScopeRules(),
            'cells' => ['required', 'array', 'min:1', 'max:5000'],
            'cells.*.time_slot_id' => ['required', 'integer'],
            'cells.*.room_id' => ['required', 'integer'],
            'cells.*.study_program_id' => ['present', 'nullable', 'integer'],
        ];
    }

    /**
     * @return list<array{time_slot_id: int, room_id: int, study_program_id: int|null}>
     */
    public function cells(): array
    {
        return array_map(fn (array $cell) => [
            'time_slot_id' => (int) $cell['time_slot_id'],
            'room_id' => (int) $cell['room_id'],
            'study_program_id' => $cell['study_program_id'] !== null ? (int) $cell['study_program_id'] : null,
        ], array_values($this->validated('cells')));
    }
}
