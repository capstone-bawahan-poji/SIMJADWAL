<?php

namespace App\Data\MasterData;

use App\Models\MasterData\AcademicPeriod;
use Spatie\LaravelData\Data;

class AcademicPeriodFormData extends Data
{
    public function __construct(
        public string $code,
        public string $startDate,
        public string $endDate,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?AcademicPeriod $current = null): self
    {
        return new self(
            code: $input['code'] ?? $current?->code,
            startDate: $input['start_date'] ?? $current?->start_date->toDateString(),
            endDate: $input['end_date'] ?? $current?->end_date->toDateString(),
        );
    }
}