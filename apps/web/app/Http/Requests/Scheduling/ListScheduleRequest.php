<?php

namespace App\Http\Requests\Scheduling;

use App\Permissions\Scheduling\SchedulingPermissions;
use App\Rules\Scheduling\PeriodRule;
use App\Services\Scheduling\ScheduleQueryService;
use Illuminate\Foundation\Http\FormRequest;

class ListScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(SchedulingPermissions::VIEW_SCHEDULE);
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'faculty_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'between:1,14'],
            'class_number' => ['nullable', 'integer', 'min:1'],
            'lecturer_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'day' => ['nullable', 'integer', 'between:1,7'],
            'unassigned' => ['nullable', 'boolean'],
            'over_capacity' => ['nullable', 'boolean'],
            // Picked classes: "12" = every class of course 12, "12:1" = class A of course 12.
            'classes' => ['nullable', 'array', 'max:100'],
            'classes.*' => ['string', 'regex:/^\d+(:\d+)?$/'],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }

    /**
     * @return array<string, mixed> FILTERS => int|null, FLAGS => bool, classes => list<array{course_id: int, class_number: int|null}>
     */
    public function filters(): array
    {
        return collect(ScheduleQueryService::FILTERS)
            ->mapWithKeys(fn (string $key) => [$key => $this->validated($key) !== null ? (int) $this->validated($key) : null])
            ->merge(collect(ScheduleQueryService::FLAGS)->mapWithKeys(fn (string $key) => [$key => $this->boolean($key)]))
            ->put('classes', collect($this->validated('classes') ?? [])
                ->map(function (string $class) {
                    [$courseId, $classNumber] = array_pad(explode(':', $class, 2), 2, null);

                    return ['course_id' => (int) $courseId, 'class_number' => $classNumber !== null ? (int) $classNumber : null];
                })->unique()->values()->all())
            ->all();
    }
}
