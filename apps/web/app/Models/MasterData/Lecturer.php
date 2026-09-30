<?php

namespace App\Models\MasterData;

use App\Models\Constraint\LecturerPreference;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\User;
use App\Policies\MasterData\LecturerPolicy;
use Database\Factories\MasterData\LecturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'study_program_id', 'code', 'name', 'title'])]
#[UsePolicy(LecturerPolicy::class)]
class Lecturer extends Model
{
    /** @use HasFactory<LecturerFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return HasMany<LecturerPreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(LecturerPreference::class);
    }

    /** @return HasMany<ScheduleDetail, $this> */
    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}
