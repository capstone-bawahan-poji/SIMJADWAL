<?php

namespace App\Http\Requests\Constraint;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConstraintWeightRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weight' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
