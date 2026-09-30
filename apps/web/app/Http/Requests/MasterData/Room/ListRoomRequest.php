<?php

namespace App\Http\Requests\MasterData\Room;

use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;

class ListRoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'faculty_id' => ['nullable', 'integer'],
            'shared' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
