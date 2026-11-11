<?php

namespace App\Models\Scheduling;

use App\Enums\Scheduling\RunSource;
use App\Enums\Scheduling\RunStatus;
use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Policies\Scheduling\SchedulingRunPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope: TPB (faculty_id and study_program_id null) or one study program (both set,
 * faculty_id = the program's faculty). Program runs are published by their faculty.
 */
#[Fillable([
    'batch_id',
    'faculty_id',
    'study_program_id',
    'source',
    'period',
    'preset_id',
    'parameter_snapshot',
    'status',
    'current_generation',
    'current_fitness',
    'best_fitness',
    'max_fitness',
    'best_generation',
    'fitness_history',
    'error_message',
    'is_active',
    'triggered_by',
    'started_at',
    'finished_at',
    'published_by',
    'published_at',
    'client_run_id',
    'dataset_fingerprint',
    'tpb_run_id',
])]
#[UsePolicy(SchedulingRunPolicy::class)]
class SchedulingRun extends Model
{
    use BroadcastsChanges;

    protected $attributes = [
        'status' => 'queued',
        'source' => 'ga',
        'current_generation' => 0,
        'is_active' => false,
    ];

    protected function casts(): array
    {
        return [
            'parameter_snapshot' => 'array',
            'fitness_history' => 'array',
            'status' => RunStatus::class,
            'source' => RunSource::class,
            'current_generation' => 'integer',
            'current_fitness' => 'decimal:4',
            'best_fitness' => 'decimal:4',
            'max_fitness' => 'decimal:4',
            'best_generation' => 'integer',
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', RunStatus::DONE)->where('is_active', false)->whereNull('published_at');
    }

    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->where('is_active', false)->whereNotNull('published_at');
    }

    /** @return Attribute<float|null, never> */
    protected function fitnessPercent(): Attribute
    {
        return Attribute::get(fn () => $this->best_fitness !== null && (float) $this->max_fitness > 0
            ? (float) $this->best_fitness / (float) $this->max_fitness * 100
            : null);
    }

    /** @return Attribute<int|null, never> */
    protected function durationSeconds(): Attribute
    {
        return Attribute::get(fn () => $this->started_at && $this->finished_at
            ? (int) $this->started_at->diffInSeconds($this->finished_at)
            : null);
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function isTpb(): bool
    {
        return $this->study_program_id === null && $this->faculty_id === null;
    }

    /** @return BelongsTo<StudyProgram, $this> */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /** @return BelongsTo<GaParameterPreset, $this> */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(GaParameterPreset::class, 'preset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<ScheduleDetail, $this> */
    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(ScheduleDetail::class);
    }

    /** @return HasMany<ViolationLog, $this> */
    public function violationLogs(): HasMany
    {
        return $this->hasMany(ViolationLog::class);
    }
}
