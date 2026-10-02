<?php

namespace App\Http\Requests\MasterData\Department;

use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'faculty_id' => ['sometimes', 'required', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(Department::class, 'code')->withoutTrashed()->ignore($department)],
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique(Department::class, 'name')->withoutTrashed()->ignore($department)],
        ];
    }
}
