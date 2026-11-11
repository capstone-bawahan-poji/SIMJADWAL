<?php

namespace App\Data\Scheduling;

use App\Enums\Scheduling\SelectionOperator;
use App\Models\MasterData\StudyProgram;
use Spatie\LaravelData\Data;

class GaPresetFormData extends Data
{
    /**
     * @param  array<string, array<string, float>>  $scOverrides
     */
    public function __construct(
        public ?int $facultyId,
        public ?int $studyProgramId,
        public string $name,
        public int $populationSize,
        public int $minGenerations,
        public int $maxGenerations,
        public int $stagnationLimit,
        public float $crossoverRate,
        public float $mutationRate,
        public int $tournamentSize,
        public SelectionOperator $selectionOperator,
        public int $parallelClassLimit,
        public int $fitnessConstant,
        public int $baseSeed,
        public int $runCount,
        public array $scOverrides,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request (PUT: every field present)
     */
    public static function fromInput(array $input, ?StudyProgram $studyProgram): self
    {
        return new self(
            facultyId: $studyProgram?->faculty_id,
            studyProgramId: $studyProgram?->id,
            name: $input['name'],
            populationSize: (int) $input['population_size'],
            minGenerations: (int) $input['min_generations'],
            maxGenerations: (int) $input['max_generations'],
            stagnationLimit: (int) $input['stagnation_limit'],
            crossoverRate: (float) $input['crossover_rate'],
            mutationRate: (float) $input['mutation_rate'],
            tournamentSize: (int) $input['tournament_size'],
            selectionOperator: SelectionOperator::from($input['selection_operator']),
            parallelClassLimit: (int) $input['parallel_class_limit'],
            fitnessConstant: (int) $input['fitness_constant'],
            baseSeed: (int) $input['base_seed'],
            runCount: (int) $input['run_count'],
            scOverrides: collect($input['sc_overrides'] ?? [])
                ->map(fn (array $weights) => array_map('floatval', $weights))
                ->all(),
        );
    }
}
