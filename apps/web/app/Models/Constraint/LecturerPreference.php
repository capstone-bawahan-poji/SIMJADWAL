<?php

namespace App\Models\Constraint;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\TimeSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lecturer's wish (SC_INGIN) or avoidance (SC_HINDARI) for one time slot.
 */
#[Fillable(['lecturer_id', 'constraint_type_id', 'time_slot_id'])]
class LecturerPreference extends Model
{
    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<ConstraintType, $this> */
    public function constraintType(): BelongsTo
    {
        return $this->belongsTo(ConstraintType::class);
    }

    /** @return BelongsTo<TimeSlot, $this> */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }
}
