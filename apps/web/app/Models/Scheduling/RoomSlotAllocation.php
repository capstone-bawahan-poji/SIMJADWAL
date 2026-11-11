<?php

namespace App\Models\Scheduling;

use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell (period, slot, room) a faculty hands to one of its study programs. The program's GA
 * may only place meetings in its cells. A cell belongs to one program across the institute.
 */
#[Fillable(['period', 'faculty_id', 'study_program_id', 'time_slot_id', 'room_id', 'slot_request_id', 'allocated_by'])]
class RoomSlotAllocation extends Model
{
    use BroadcastsChanges;

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return BelongsTo<StudyProgram, $this> */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /** @return BelongsTo<TimeSlot, $this> */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<SlotRequest, $this> */
    public function slotRequest(): BelongsTo
    {
        return $this->belongsTo(SlotRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
