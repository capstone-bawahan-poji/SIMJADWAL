<?php

namespace App\Services\Scheduling;

use App\Data\Scheduling\GaPresetData;
use App\Data\Scheduling\GaPresetFormData;
use App\Enums\MasterData\ReferenceType;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\User;
use App\Services\MasterData\ReferenceGuard;
use Illuminate\Support\Collection;

readonly class GaPresetService
{
    public function __construct(
        private GaParameterPreset $preset,
        private ReferenceGuard $referenceGuard,
        private GaPresetActivityLogService $activityLog,
    ) {}

    /**
     * @return Collection<int, GaPresetData>
     */
    public function getPresets(int $facultyId): Collection
    {
        return $this->preset->newQuery()
            ->where('faculty_id', $facultyId)
            ->orderBy('name')
            ->get()
            ->map(fn (GaParameterPreset $preset) => GaPresetData::from($preset));
    }

    public function getPreset(GaParameterPreset $preset): GaPresetData
    {
        return GaPresetData::from($preset);
    }

    public function createPreset(GaPresetFormData $data, User $actor): GaPresetData
    {
        $preset = $this->preset->newQuery()->create([...$this->attributes($data), 'created_by' => $actor->id]);
        $this->activityLog->created($preset);

        return $this->getPreset($preset);
    }

    public function updatePreset(GaParameterPreset $preset, GaPresetFormData $data): GaPresetData
    {
        $preset->update($this->attributes($data));
        $this->activityLog->updated($preset);

        return $this->getPreset($preset->fresh());
    }

    public function deletePreset(GaParameterPreset $preset): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::SCHEDULING_RUNS->value => $preset->schedulingRuns()->count(),
        ]);

        $this->activityLog->deleted($preset);
        $preset->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(GaPresetFormData $data): array
    {
        return [
            'faculty_id' => $data->facultyId,
            'name' => $data->name,
            'population_size' => $data->populationSize,
            'min_generations' => $data->minGenerations,
            'max_generations' => $data->maxGenerations,
            'stagnation_limit' => $data->stagnationLimit,
            'crossover_rate' => $data->crossoverRate,
            'mutation_rate' => $data->mutationRate,
            'tournament_size' => $data->tournamentSize,
            'selection_operator' => $data->selectionOperator,
            'parallel_class_limit' => $data->parallelClassLimit,
            'fitness_constant' => $data->fitnessConstant,
            'base_seed' => $data->baseSeed,
            'run_count' => $data->runCount,
            'sc_overrides' => (object) $data->scOverrides,
        ];
    }
}