<?php

namespace App\Http\Requests\Scheduling;

use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Both fields are sent every time: an override always states the full new place.
 * room_id null leaves the class without a room, as the GA does when none fits.
 * Lecturers and students already see a published schedule, so changing it needs a reason.
 */
class OverrideScheduleDetailRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var ScheduleDetail $detail */
        $detail = $this->route('scheduleDetail');

        return [
            'time_slot_id' => ['required', 'integer', Rule::exists(TimeSlot::class, 'id')],
            'room_id' => ['present', 'nullable', 'integer', Rule::exists(Room::class, 'id')->withoutTrashed()],
            'reason' => [Rule::requiredIf(fn () => $detail->schedulingRun->published_at !== null), 'nullable', 'string', 'max:500'],
        ];
    }
}