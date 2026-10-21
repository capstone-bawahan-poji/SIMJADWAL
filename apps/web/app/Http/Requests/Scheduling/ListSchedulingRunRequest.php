<?php

namespace App\Http\Requests\Scheduling;

use App\Http\Queries\Query;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

class ListSchedulingRunRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => ['nullable', 'integer'],
            'period' => ['nullable', new PeriodRule],
            'batch_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
