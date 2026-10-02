<?php

namespace App\Data\MasterData;

use App\Models\MasterData\Room;
use Spatie\LaravelData\Data;

/**
 * faculty_id null = shared room.
 */
class RoomFormData extends Data
{
    public function __construct(
        public ?int $facultyId,
        public string $code,
        public ?string $name,
        public ?string $building,
        public ?int $floor,
        public int $capacity,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?int $facultyId, ?Room $current = null): self
    {
        return new self(
            facultyId: $facultyId,
            code: $input['code'] ?? $current?->code,
            name: array_key_exists('name', $input) ? $input['name'] : $current?->name,
            building: array_key_exists('building', $input) ? $input['building'] : $current?->building,
            floor: array_key_exists('floor', $input) ? $input['floor'] : $current?->floor,
            capacity: (int) ($input['capacity'] ?? $current?->capacity),
        );
    }
}
