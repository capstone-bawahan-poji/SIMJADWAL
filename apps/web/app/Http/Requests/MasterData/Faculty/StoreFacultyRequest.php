<?php

namespace App\Http\Requests\MasterData\Faculty;

use App\Models\MasterData\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacultyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique(Faculty::class, 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:150', Rule::unique(Faculty::class, 'name')->withoutTrashed()],
        ];
    }
}
