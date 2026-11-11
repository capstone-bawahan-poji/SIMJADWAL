<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * needs empty or missing = the shortages of the sufficiency check.
 */
class StoreSlotRequestRequest extends FormRequest
{
    use ResolvesScheduleScope;

    public function rules(): array
    {
        return [
            'period' => ['required', new PeriodRule],
            'study_program_id' => ['nullable', 'integer'],
            'needs' => ['nullable', 'array', 'max:200'],
            'needs.*.course_id' => ['required', 'integer'],
            'needs.*.meetings' => ['required', 'integer', 'min:1', 'max:100'],
            'needs.*.min_capacity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array{course_id: int, meetings: int, min_capacity: int}>
     */
    public function needs(): array
    {
        return array_map(fn (array $need) => [
            'course_id' => (int) $need['course_id'],
            'meetings' => (int) $need['meetings'],
            'min_capacity' => (int) $need['min_capacity'],
        ], array_values($this->validated('needs') ?? []));
    }
}
