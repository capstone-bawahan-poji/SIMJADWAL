<?php

namespace App\Http\Requests\Account\User;

use App\Enums\Account\Role;
use App\Models\User;
use App\Rules\Account\UserScopeRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * PATCH: every field is optional. Sending role re-validates the full scope for that role;
 * without role, scope fields are checked against the current role.
 */
class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $roleChanged = $this->filled('role');
        $role = $roleChanged ? Role::tryFrom((string) $this->input('role')) : $user->role;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:150', Rule::unique(User::class)->ignore($user)],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'role' => ['sometimes', 'required', Rule::enum(Role::class)],
            ...UserScopeRules::for($role, partial: ! $roleChanged, ignoreUserId: $user->id),
        ];
    }
}
