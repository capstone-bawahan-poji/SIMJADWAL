<?php

namespace App\Http\Requests\MasterData\TpbGroup;

use App\Http\Queries\Query;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

class ListTpbGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tpb_offering_id' => ['nullable', 'integer'],
            'period' => ['nullable', new PeriodRule],
            'study_program_id' => ['nullable', 'integer'],
            'cohort' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
