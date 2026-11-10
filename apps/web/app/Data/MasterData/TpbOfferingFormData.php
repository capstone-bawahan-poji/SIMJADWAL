<?php

namespace App\Data\MasterData;

use App\Models\MasterData\TpbOffering;
use Spatie\LaravelData\Data;

class TpbOfferingFormData extends Data
{
    public function __construct(
        public int $courseId,
        public string $period,
        public int $parallelClassCount,
        public int $classCapacity,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?TpbOffering $current = null): self
    {
        return new self(
            courseId: (int) ($input['course_id'] ?? $current?->course_id),
            period: $input['period'] ?? $current?->period,
            parallelClassCount: (int) ($input['parallel_class_count'] ?? $current?->parallel_class_count),
            classCapacity: (int) ($input['class_capacity'] ?? $current?->class_capacity),
        );
    }
}
