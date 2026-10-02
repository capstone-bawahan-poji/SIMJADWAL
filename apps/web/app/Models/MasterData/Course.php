<?php

namespace App\Models\MasterData;

use App\Models\Scheduling\ScheduleDetail;
use App\Policies\MasterData\CoursePolicy;
use Database\Factories\MasterData\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Regular courses belong to one study program. TPB/MKWU courses (is_tpb) have no program:
 * the TPB admin splits their classes into groups (TpbGroup) shared by several programs.
 */
#[Fillable(['study_program_id', 'is_tpb', 'code', 'name', 'sks', 'semester', 'parallel_class_count', 'class_capacity'])]
#[UsePolicy(CoursePolicy::class)]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_tpb' => 'boolean',
            'sks' => 'integer',
            'semester' => 'integer',
            'parallel_class_count' => 'integer',
            'class_capacity' => 'integer',
        ];
    }

    /**
     * 4 SKS = 2 meetings a week, 2 and 3 SKS = 1 meeting.
     *
     * @return Attribute<int, never>
     */
    protected function meetingCount(): Attribute
    {
        return Attribute::get(fn () => $this->sks === 4 ? 2 : 1);
    }

    /**
     * Slot type (in SKS) each meeting should land on for SC_SKS.
     *
     * @return Attribute<int, never>
     */
    protected function targetSlotType(): Attribute
    {
        return Attribute::get(fn () => $this->sks === 3 ? 3 : 2);
    }

    /** @return BelongsTo<StudyProgram, $this> */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /** @return HasMany<CourseLecturer, $this> */
    public function courseLecturers(): HasMany
    {
        return $this->hasMany(CourseLecturer::class);
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
