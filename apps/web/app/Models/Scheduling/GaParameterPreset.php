<?php

namespace App\Models\Scheduling;

use App\Enums\Scheduling\SelectionOperator;
use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\Faculty;
use App\Models\User;
use App\Policies\Scheduling\GaParameterPresetPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'faculty_id',
    'name',
    'population_size',
    'min_generations',
    'max_generations',
    'stagnation_limit',
    'crossover_rate',
    'mutation_rate',
    'tournament_size',
    'selection_operator',
    'parallel_class_limit',
    'fitness_constant',
    'base_seed',
    'run_count',
    'sc_overrides',
    'created_by',
])]
#[UsePolicy(GaParameterPresetPolicy::class)]
class GaParameterPreset extends Model
{
    use BroadcastsChanges;

    protected $attributes = [
        'sc_overrides' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'population_size' => 'integer',
            'min_generations' => 'integer',
            'max_generations' => 'integer',
            'stagnation_limit' => 'integer',
            'crossover_rate' => 'decimal:3',
            'mutation_rate' => 'decimal:3',
            'tournament_size' => 'integer',
            'selection_operator' => SelectionOperator::class,
            'parallel_class_limit' => 'integer',
            'fitness_constant' => 'integer',
            'base_seed' => 'integer',
            'run_count' => 'integer',
            'sc_overrides' => 'array',
        ];
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SchedulingRun, $this> */
    public function schedulingRuns(): HasMany
    {
        return $this->hasMany(SchedulingRun::class, 'preset_id');
    }
}