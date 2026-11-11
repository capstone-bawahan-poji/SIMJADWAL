<?php

namespace App\Http\Requests\Scheduling;

use App\Http\Queries\Query;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSchedulingRunRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'scope' => ['nullable', Rule::in(['study_program', 'tpb'])],
            'period' => ['nullable', new PeriodRule],
            'batch_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
