<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * dry_run = preview, nothing is saved. Used by the TPB admin import (a TPB run) and, with
 * faculty_id for those who read every faculty, the faculty import.
 */
class ImportTpbScheduleRequest extends FormRequest
{
    use ResolvesFacultyScope;

    public function rules(): array
    {
        return [
            'period' => ['required', new PeriodRule],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:4096'],
            'dry_run' => ['required', 'boolean'],
            'faculty_id' => ['nullable', 'integer'],
        ];
    }
}
