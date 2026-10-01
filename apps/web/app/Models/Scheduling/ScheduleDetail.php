<?php

namespace App\Models\Scheduling;

use App\Models\MasterData\Course;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One gene = one meeting of one class. course_id, class_number and lecturer_id are
 * snapshots taken when the run finished. Overrides may change only time_slot_id and room_id.
 */
#[Fillable([
    'scheduling_run_id', 'course_id', 'class_number', 'meeting_number', 'lecturer_id',
    'time_slot_id', 'room_id', 'is_overridden', 'overridden_by', 'overridden_at',
])]
#[WithoutTimestamps]
class ScheduleDetail extends Model
{
    protected function casts(): array
    {
        return [
            'class_number' => 'integer',
            'meeting_number' => 'integer',
            'is_overridden' => 'boolean',
            'overridden_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function unassigned(Builder $query): void
    {
        $query->whereNull('room_id');
    }

    #[Scope]
    protected function overCapacity(Builder $query): void
    {
        $query->whereExists(fn (QueryBuilder $sub) => $sub
            ->selectRaw('1')
            ->from('rooms')
            ->crossJoin('courses')
            ->whereColumn('rooms.id', 'schedule_details.room_id')
            ->whereColumn('courses.id', 'schedule_details.course_id')
            ->whereColumn('courses.class_capacity', '>', 'rooms.capacity'));
    }

    /** @return BelongsTo<SchedulingRun, $this> */
    public function schedulingRun(): BelongsTo
    {
        return $this->belongsTo(SchedulingRun::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
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

    /** @return BelongsTo<User, $this> */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /** @return HasMany<ViolationLog, $this> */
    public function violationLogs(): HasMany
    {
        return $this->hasMany(ViolationLog::class);
    }
}
