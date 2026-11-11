<?php

namespace App\Http\Requests\Catalog;

use App\Services\Scheduling\Export\ScheduleExportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A student on the landing page picks classes and gets the published schedule by email.
 * classes use the ListScheduleRequest notation: "12" = every class of course 12, "12:1" = class A.
 */
class SendScheduleEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'classes' => ['required', 'array', 'min:1', 'max:100'],
            'classes.*' => ['required', 'string', 'regex:/^\d+(:\d+)?$/', 'distinct'],
            'formats' => ['required', 'array', 'min:1', 'max:'.count(ScheduleExportService::FORMATS)],
            'formats.*' => ['required', 'string', Rule::in(array_keys(ScheduleExportService::FORMATS)), 'distinct'],
        ];
    }

    /**
     * @return list<array{course_id: int, class_number: int|null}>
     */
    public function classes(): array
    {
        return collect($this->validated('classes'))
            ->map(function (string $class) {
                [$courseId, $classNumber] = array_pad(explode(':', $class, 2), 2, null);

                return ['course_id' => (int) $courseId, 'class_number' => $classNumber !== null ? (int) $classNumber : null];
            })->values()->all();
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return array_values($this->validated('formats'));
    }
}
