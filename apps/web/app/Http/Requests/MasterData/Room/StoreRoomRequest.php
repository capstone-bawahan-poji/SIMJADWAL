<?php

namespace App\Http\Requests\MasterData\Room;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * faculty_id absent: the faculty admin's own faculty, or a shared room for superadmin.
 * faculty_id null: a shared room (superadmin only, checked by RoomPolicy).
 */
class StoreRoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'faculty_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()],
            'code' => ['required', 'string', 'max:20', Rule::unique(Room::class, 'code')->withoutTrashed()],
            'name' => ['nullable', 'string', 'max:100'],
            'building' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'integer', 'between:0,50'],
            'capacity' => ['required', 'integer', 'between:1,10000'],
        ];
    }
}
