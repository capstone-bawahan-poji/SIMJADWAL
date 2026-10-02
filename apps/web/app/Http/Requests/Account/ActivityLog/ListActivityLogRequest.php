<?php

namespace App\Http\Requests\Account\ActivityLog;

use App\Http\Queries\Query;
use Illuminate\Foundation\Http\FormRequest;

class ListActivityLogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'event' => ['nullable', 'string', 'max:50'],
            'causer_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.Query::MAX_PER_PAGE],
        ];
    }
}
