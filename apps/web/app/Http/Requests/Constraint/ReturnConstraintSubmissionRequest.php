<?php

namespace App\Http\Requests\Constraint;

use Illuminate\Foundation\Http\FormRequest;

class ReturnConstraintSubmissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:1000'],
        ];
    }
}
