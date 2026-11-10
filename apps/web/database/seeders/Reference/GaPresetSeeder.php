<?php

namespace Database\Seeders\Reference;

use App\Enums\Scheduling\SelectionOperator;
use App\Models\MasterData\Faculty;
use App\Models\Scheduling\GaParameterPreset;
use Illuminate\Database\Seeder;

/**
 * Two presets per faculty, and two for TPB (faculty null), with the best combinations from Laporan TA kak amrina
 */
class GaPresetSeeder extends Seeder
{
    public function run(): void
    {
        $base = [
            'population_size' => 100,
            'min_generations' => 500,
            'max_generations' => 1000,
            'stagnation_limit' => 200,
            'tournament_size' => 2,
            'parallel_class_limit' => 5,
            'fitness_constant' => 500,
            'base_seed' => 12345,
            'run_count' => 10,
        ];

        $presets = [
            'Preset 1 (TS)' => ['crossover_rate' => 0.9, 'mutation_rate' => 0.5, 'selection_operator' => SelectionOperator::TS],
            'Preset 2 (TSR)' => ['crossover_rate' => 0.4, 'mutation_rate' => 0.3, 'selection_operator' => SelectionOperator::TSR],
        ];

        foreach ([...Faculty::query()->pluck('id')->all(), null] as $facultyId) {
            foreach ($presets as $name => $values) {
                GaParameterPreset::query()->firstOrCreate(
                    ['faculty_id' => $facultyId, 'name' => $name],
                    [...$base, ...$values],
                );
            }
        }
    }
}
