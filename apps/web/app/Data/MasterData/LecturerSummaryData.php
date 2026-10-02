<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class LecturerSummaryData extends BaseData
{
    public function __construct(
        public int $id,
        public int $studyProgramId,
        public string $nip,
        public string $name,
        public ?string $title,
    ) {}
}
