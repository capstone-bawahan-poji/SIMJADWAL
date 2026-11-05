<?php

namespace App\Http\Requests\MasterData\AcademicPeriod;

use App\Models\MasterData\AcademicPeriod;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH: every field is optional. The date order is checked on the merged values.
 */
class UpdateAcademicPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var AcademicPeriod $period */
        $period = $this->route('academicPeriod');

        return [
            'code' => ['sometimes', 'required', 'string', new PeriodRule, Rule::unique(AcademicPeriod::class)->ignore($period->id)],
            'start_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var AcademicPeriod $period */
            $period = $this->route('academicPeriod');
            if ($this->input('end_date', $period->end_date->toDateString()) <= $this->input('start_date', $period->start_date->toDateString())) {
                $validator->errors()->add('end_date', __('The end date must be after the start date.'));
            }
        }];
    }
}