<?php

namespace App\Http\Requests\MasterData\Faculty;

use App\Models\MasterData\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacultyRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Faculty $faculty */
        $faculty = $this->route('faculty');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(Faculty::class, 'code')->withoutTrashed()->ignore($faculty)],
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique(Faculty::class, 'name')->withoutTrashed()->ignore($faculty)],
        ];
    }
}
