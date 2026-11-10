<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ListCatalogCoursesRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'faculty_id' => ['nullable', 'integer'],
            'study_program_id' => ['nullable', 'integer'],
            'semester' => ['nullable', 'integer', 'between:1,8'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }
}
