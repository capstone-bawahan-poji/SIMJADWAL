<?php

namespace App\Http\Requests\MasterData\TpbOffering;

use App\Http\Queries\Query;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

class ListTpbOfferingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'course_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
