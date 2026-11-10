<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A program attending a TPB group. target_semester is null for mengulang groups.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TpbParticipantData extends BaseData
{
    public function __construct(
        public int $studyProgramId,
        public string $code,
        public string $name,
        public ?int $targetSemester,
    ) {}
}
