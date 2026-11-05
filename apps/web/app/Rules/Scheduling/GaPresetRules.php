<?php

namespace App\Rules\Scheduling;

use App\Enums\Constraint\ConstraintCategory;
use App\Enums\Scheduling\SelectionOperator;
use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\GaParameterPreset;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class GaPresetRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function attributes(?int $facultyId, ?GaParameterPreset $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique(GaParameterPreset::class, 'name')->where('faculty_id', $facultyId)->ignore($ignore)],
            'population_size' => ['required', 'integer', 'between:2,10000'],
            'min_generations' => ['required', 'integer', 'min:1'],
            'max_generations' => ['required', 'integer', 'gte:min_generations', 'max:100000'],
            'stagnation_limit' => ['required', 'integer', 'min:1'],
            'crossover_rate' => ['required', 'numeric', 'between:0,1'],
            'mutation_rate' => ['required', 'numeric', 'between:0,1'],
            'tournament_size' => ['required', 'integer', 'min:2', 'lte:population_size'],
            'selection_operator' => ['required', Rule::enum(SelectionOperator::class)],
            'parallel_class_limit' => ['required', 'integer', 'between:1,100'],
            'fitness_constant' => ['required', 'integer', 'min:1'],
            'base_seed' => ['required', 'integer', 'min:0'],
            'run_count' => ['required', 'integer', 'between:1,10'],
            'sc_overrides' => ['present', 'array'],
        ];
    }

    /**
     * sc_overrides keys must be study programs of the faculty, inner keys soft constraint codes,
     * values above 0. Hard constraints are never overridden per program.
     */
    public static function validateScOverrides(Validator $validator, ?int $facultyId): void
    {
        $overrides = $validator->getData()['sc_overrides'] ?? null;

        if (! is_array($overrides) || $overrides === [] || $facultyId === null) {
            return;
        }

        $programIds = StudyProgram::query()->where('faculty_id', $facultyId)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $softCodes = ConstraintType::query()->where('category', ConstraintCategory::SOFT)->pluck('code')->map(fn ($code) => $code->value)->all();
        $hardCodes = ConstraintType::query()->where('category', ConstraintCategory::HARD)->pluck('code')->map(fn ($code) => $code->value)->all();

        foreach ($overrides as $programId => $weights) {
            $key = "sc_overrides.{$programId}";

            if (! in_array((string) $programId, $programIds, true)) {
                $validator->errors()->add($key, __('The study program does not belong to this faculty.'));

                continue;
            }

            if (! is_array($weights)) {
                $validator->errors()->add($key, __('The override must map constraint codes to weights.'));

                continue;
            }

            foreach ($weights as $code => $weight) {
                if (in_array($code, $hardCodes, true)) {
                    $validator->errors()->add("{$key}.{$code}", __('Hard constraints cannot be overridden per study program.'));
                } elseif (! in_array($code, $softCodes, true)) {
                    $validator->errors()->add("{$key}.{$code}", __('Unknown soft constraint code.'));
                } elseif (! is_numeric($weight) || (float) $weight <= 0) {
                    $validator->errors()->add("{$key}.{$code}", __('The override weight must be greater than 0.'));
                }
            }
        }
    }
}
