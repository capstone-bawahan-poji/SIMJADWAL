<?php

namespace App\Models\Scheduling;

use App\Enums\Scheduling\SelectionOperator;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'population_size', 'min_generations', 'max_generations', 'stagnation_limit',
    'crossover_rate', 'mutation_rate', 'tournament_size', 'selection_operator',
    'parallel_class_limit', 'created_by',
])]
class GaParameterPreset extends Model
{
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
        ];
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
