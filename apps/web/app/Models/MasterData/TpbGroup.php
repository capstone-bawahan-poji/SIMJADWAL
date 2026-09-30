<?php

namespace App\Models\MasterData;

use App\Policies\MasterData\TpbGroupPolicy;
use Database\Factories\MasterData\TpbGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One group of a TPB course, e.g. Kalkulus A-E for IF, SI, MA, PK and MS.
 * Every class of the group runs in time_slot_id, so the slot is blocked for all
 * participating programs in the course's semester (see TpbBlockService).
 */
#[Fillable(['course_id', 'code', 'time_slot_id'])]
#[UsePolicy(TpbGroupPolicy::class)]
class TpbGroup extends Model
{
    /** @use HasFactory<TpbGroupFactory> */
    use HasFactory;

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<TimeSlot, $this> */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /** @return BelongsToMany<StudyProgram, $this> */
    public function studyPrograms(): BelongsToMany
    {
        return $this->belongsToMany(StudyProgram::class, 'tpb_group_study_programs')->withTimestamps();
    }

    /** @return HasMany<CourseLecturer, $this> */
    public function courseLecturers(): HasMany
    {
        return $this->hasMany(CourseLecturer::class);
    }
}
