<?php

namespace App\Http\Requests\MasterData\Department;

use Illuminate\Foundation\Http\FormRequest;

class ListDepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'faculty_id' => ['nullable', 'integer'],
        ];
    }
}
