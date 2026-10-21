<?php

namespace App\Data\Scheduling;

use App\Data\BaseData;
use App\Enums\Scheduling\SelectionOperator;
use App\Extensions\Data\FromPolicy;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * sc_overrides: {"<study_program_id>": {"<SC code>": weight}}. Missing entries use the global weight.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class GaPresetData extends BaseData
{
    /**
     * @param  array<string, array<string, float>>  $scOverrides
     */
    public function __construct(
        public int $id,
        public int $facultyId,
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
        public ?int $createdBy,
        public ?CarbonImmutable $updatedAt,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}
}
