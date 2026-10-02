<?php

namespace App\Http\Requests\Account\User;

use App\Enums\Account\Role;
use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'is_active' => ['nullable', 'boolean'],
            'faculty_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
