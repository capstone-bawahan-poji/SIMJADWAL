<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Scheduling\SlotRequestStatus;
use App\Http\Queries\Query;
use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSlotRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'status' => ['nullable', Rule::enum(SlotRequestStatus::class)],
            'study_program_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
