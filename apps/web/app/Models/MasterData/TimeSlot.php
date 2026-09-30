<?php

namespace App\Models\MasterData;

use App\Enums\MasterData\Day;
use App\Models\Constraint\LecturerPreference;
use App\Models\Scheduling\ScheduleDetail;
use App\Policies\MasterData\TimeSlotPolicy;
use Database\Factories\MasterData\TimeSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * type = slot length in SKS (2 or 3), evaluated by SC_SKS.
 */
#[Fillable(['day', 'session', 'start_time', 'end_time', 'type'])]
#[UsePolicy(TimeSlotPolicy::class)]
class TimeSlot extends Model
{
    /** @use HasFactory<TimeSlotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'day' => Day::class,
            'session' => 'integer',
            'type' => 'integer',
        ];
    }

    /**
     * PostgreSQL returns TIME as HH:MM:SS. The API and validation use HH:MM.
     *
     * @return Attribute<string, string>
     */
    protected function startTime(): Attribute
    {
        return Attribute::get(fn (string $value) => substr($value, 0, 5));
    }

    /** @return Attribute<string, string> */
    protected function endTime(): Attribute
    {
        return Attribute::get(fn (string $value) => substr($value, 0, 5));
    }

    /** @return HasMany<LecturerPreference, $this> */
    public function lecturerPreferences(): HasMany
    {
        return $this->hasMany(LecturerPreference::class);
    }

    /** @return HasMany<TpbGroup, $this> */
    public function tpbGroups(): HasMany
    {
        return $this->hasMany(TpbGroup::class);
    }

    /** @return HasMany<ScheduleDetail, $this> */
    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}
