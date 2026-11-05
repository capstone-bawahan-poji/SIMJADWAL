<?php

namespace App\Http\Requests\MasterData\AcademicPeriod;

use App\Models\MasterData\AcademicPeriod;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', new PeriodRule, Rule::unique(AcademicPeriod::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ];
    }
}