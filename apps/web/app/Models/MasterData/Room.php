<?php

namespace App\Models\MasterData;

use App\Models\Scheduling\ScheduleDetail;
use App\Policies\MasterData\RoomPolicy;
use Database\Factories\MasterData\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * faculty_id NULL = room shared across faculties.
 */
#[Fillable(['faculty_id', 'code', 'name', 'capacity'])]
#[UsePolicy(RoomPolicy::class)]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function isShared(): bool
    {
        return $this->faculty_id === null;
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return HasMany<CourseLecturer, $this> TPB classes placed in this room */
    public function courseLecturers(): HasMany
    {
        return $this->hasMany(CourseLecturer::class);
    }

    /** @return HasMany<ScheduleDetail, $this> */
    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}
