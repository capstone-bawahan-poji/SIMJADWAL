<?php

namespace App\Http\Requests\Account\User;

use App\Enums\Account\Role;
use App\Models\User;
use App\Rules\Account\UserScopeRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', Rule::unique(User::class)],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
            ...UserScopeRules::for(Role::tryFrom((string) $this->input('role'))),
        ];
    }
}
