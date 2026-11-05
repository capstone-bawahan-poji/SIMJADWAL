<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use App\Services\Scheduling\Period;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dates are YYYY-MM-DD. label = "Ganjil 2026/2027"; is_current = the period the apps load by default.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class AcademicPeriodData extends BaseData
{
    #[Computed]
    public string $label;

    #[Computed]
    public bool $isCurrent;

    public function __construct(
        public int $id,
        public string $code,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public Carbon $startDate,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public Carbon $endDate,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {
        $this->label = Period::label($code);
        $this->isCurrent = $code === config('scheduling.current_period');
    }
}