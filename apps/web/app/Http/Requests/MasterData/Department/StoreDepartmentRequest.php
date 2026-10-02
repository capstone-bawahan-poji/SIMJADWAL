<?php

namespace App\Http\Requests\MasterData\Department;

use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()],
            'code' => ['required', 'string', 'max:20', Rule::unique(Department::class, 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150', Rule::unique(Department::class, 'name')->withoutTrashed()],
        ];
    }
}
