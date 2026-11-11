<?php

namespace App\Models\Scheduling;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tpb_occupancy_import_id', 'time_slot_id', 'room_id', 'lecturer_id', 'course_code', 'course_name', 'class_label', 'participants'])]
#[WithoutTimestamps]
class TpbOccupancyImportRow extends Model
{
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

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
