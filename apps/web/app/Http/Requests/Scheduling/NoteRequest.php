<?php

namespace App\Http\Requests\Scheduling;

use App\Rules\Scheduling\PeriodRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A step that must say why: returning a schedule, rejecting a slot request, withdrawing a publication.
 */
class NoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', new PeriodRule],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? config('scheduling.current_period');
    }
}
