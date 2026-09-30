<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Enums\MasterData\Day;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * type = slot length in SKS (2 or 3). Times are HH:MM.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TimeSlotData extends BaseData
{
    #[Computed]
    public string $dayLabel;

    public function __construct(
        public int $id,
        public Day $day,
        public int $session,
        public string $startTime,
        public string $endTime,
        public int $type,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {
        $this->dayLabel = $day->label();
    }
}
