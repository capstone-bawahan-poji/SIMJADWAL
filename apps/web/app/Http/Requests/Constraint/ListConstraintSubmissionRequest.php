<?php

namespace App\Http\Requests\Constraint;

use App\Enums\Constraint\ConstraintStatus;
use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListConstraintSubmissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::enum(ConstraintStatus::class)],
            'faculty_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
