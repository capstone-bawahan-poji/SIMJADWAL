<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Enums\MasterData\Day;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TimeSlotSummaryData extends BaseData
{
    public function __construct(
        public int $id,
        public Day $day,
        public int $session,
        public string $startTime,
        public string $endTime,
        public int $type,
    ) {}
}
