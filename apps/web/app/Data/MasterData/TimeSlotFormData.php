<?php

namespace App\Data\MasterData;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use Spatie\LaravelData\Data;

class TimeSlotFormData extends Data
{
    public function __construct(
        public Day $day,
        public int $session,
        public string $startTime,
        public string $endTime,
        public int $type,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?TimeSlot $current = null): self
    {
        return new self(
            day: isset($input['day']) ? Day::from((int) $input['day']) : $current?->day,
            session: (int) ($input['session'] ?? $current?->session),
            startTime: $input['start_time'] ?? $current?->start_time,
            endTime: $input['end_time'] ?? $current?->end_time,
            type: (int) ($input['type'] ?? $current?->type),
        );
    }
}
