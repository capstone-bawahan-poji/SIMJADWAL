<?php

namespace App\Http\Requests\MasterData\TpbOffering;

use App\Models\MasterData\Course;
use App\Models\MasterData\TpbOffering;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTpbOfferingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'course_id' => [
                'required', 'integer',
                Rule::exists(Course::class, 'id')->withoutTrashed()->where('is_tpb', true),
                Rule::unique(TpbOffering::class)->where('period', $this->input('period')),
            ],
            'period' => ['required', new PeriodRule],
            'parallel_class_count' => ['required', 'integer', 'between:1,99'],
            'class_capacity' => ['required', 'integer', 'between:1,1000'],
        ];
    }
}
