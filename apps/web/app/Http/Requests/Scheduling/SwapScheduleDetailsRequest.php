<?php

namespace App\Http\Requests\Scheduling;

use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapScheduleDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'a' => ['required', 'integer', Rule::exists(ScheduleDetail::class, 'id')],
            'b' => ['required', 'integer', 'different:a', Rule::exists(ScheduleDetail::class, 'id')],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function detailA(): ScheduleDetail
    {
        return ScheduleDetail::query()->findOrFail($this->validated('a'));
    }

    public function detailB(): ScheduleDetail
    {
        return ScheduleDetail::query()->findOrFail($this->validated('b'));
    }
}
