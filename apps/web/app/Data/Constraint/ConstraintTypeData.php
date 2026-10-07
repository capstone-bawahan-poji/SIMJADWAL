<?php

namespace App\Data\Constraint;

use App\Data\BaseData;
use App\Enums\Constraint\ConstraintCategory;
use App\Enums\Constraint\ConstraintCode;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * can_update is false for hard constraints and for roles that may not change weights.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class ConstraintTypeData extends BaseData
{
    public function __construct(
        public int $id,
        public ConstraintCode $code,
        public ConstraintCategory $category,
        public string $description,
        public float $weight,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
    ) {}
}
