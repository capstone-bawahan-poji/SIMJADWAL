<?php

namespace App\Models\MasterData;

use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Policies\MasterData\FacultyPolicy;
use Database\Factories\MasterData\FacultyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
#[UsePolicy(FacultyPolicy::class)]
class Faculty extends Model
{
    /** @use HasFactory<FacultyFactory> */
    use HasFactory;

    /** @return HasMany<StudyProgram, $this> */
    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** @return HasMany<SchedulingRun, $this> */
    public function schedulingRuns(): HasMany
    {
        return $this->hasMany(SchedulingRun::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
