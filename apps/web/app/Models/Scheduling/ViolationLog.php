<?php

namespace App\Models\Scheduling;

use App\Models\Constraint\ConstraintType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A violated HC or unmet SC of the best chromosome of a run.
 */
#[Fillable(['scheduling_run_id', 'schedule_detail_id', 'constraint_type_id', 'penalty', 'message'])]
#[WithoutTimestamps]
class ViolationLog extends Model
{
    protected function casts(): array
    {
        return [
            'penalty' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<SchedulingRun, $this> */
    public function schedulingRun(): BelongsTo
    {
        return $this->belongsTo(SchedulingRun::class);
    }

    /** @return BelongsTo<ScheduleDetail, $this> */
    public function scheduleDetail(): BelongsTo
    {
        return $this->belongsTo(ScheduleDetail::class);
    }

    /** @return BelongsTo<ConstraintType, $this> */
    public function constraintType(): BelongsTo
    {
        return $this->belongsTo(ConstraintType::class);
    }
}
