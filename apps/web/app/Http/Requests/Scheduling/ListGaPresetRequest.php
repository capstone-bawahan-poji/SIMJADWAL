<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\Account\Role;
use App\Models\MasterData\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGaPresetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => [
                Rule::requiredIf(fn () => $this->user()->role === Role::SUPER_ADMIN),
                'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed(),
            ],
        ];
    }

    public function facultyId(): ?int
    {
        return $this->user()->role === Role::SUPER_ADMIN
            ? (int) $this->validated('faculty_id')
            : $this->user()->faculty_id;
    }
}
