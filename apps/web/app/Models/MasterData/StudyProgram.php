<?php

namespace App\Models\MasterData;

use App\Enums\Constraint\ConstraintStatus;
use App\Models\User;
use App\Policies\MasterData\StudyProgramPolicy;
use Database\Factories\MasterData\StudyProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['faculty_id', 'code', 'name', 'constraint_status', 'constraint_submitted_at'])]
#[UsePolicy(StudyProgramPolicy::class)]
class StudyProgram extends Model
{
    /** @use HasFactory<StudyProgramFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'constraint_status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'constraint_status' => ConstraintStatus::class,
            'constraint_submitted_at' => 'datetime',
        ];
    }

    /**
     * Submitted programs are read-only for their master data and lecturer preferences.
     */
    public function isLocked(): bool
    {
        return $this->constraint_status === ConstraintStatus::SUBMITTED;
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return HasMany<Lecturer, $this> */
    public function lecturers(): HasMany
    {
        return $this->hasMany(Lecturer::class);
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsToMany<TpbGroup, $this> */
    public function tpbGroups(): BelongsToMany
    {
        return $this->belongsToMany(TpbGroup::class, 'tpb_group_study_programs')->withTimestamps();
    }
}
