<?php

namespace App\Http\Requests\Scheduling;

use App\Services\Scheduling\Export\ScheduleExportService;
use Illuminate\Validation\Rule;

/**
 * GET /schedules filters plus the file format.
 */
class ExportScheduleRequest extends ListScheduleRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::in(array_keys(ScheduleExportService::FORMATS))],
        ];
    }
}