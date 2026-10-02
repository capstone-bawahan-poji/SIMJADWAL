<?php

namespace App\Http\Requests\MasterData\Room;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH: every field is optional. Moving a room to another faculty (or making it shared)
 * needs create rights on the target, checked in RoomController.
 */
class UpdateRoomRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Room $room */
        $room = $this->route('room');

        return [
            'faculty_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Faculty::class, 'id')->withoutTrashed()],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique(Room::class, 'code')->withoutTrashed()->ignore($room)],
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'building' => ['sometimes', 'nullable', 'string', 'max:50'],
            'floor' => ['sometimes', 'nullable', 'integer', 'between:0,50'],
            'capacity' => ['sometimes', 'required', 'integer', 'between:1,10000'],
        ];
    }
}
