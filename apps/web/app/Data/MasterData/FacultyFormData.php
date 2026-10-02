<?php

namespace App\Data\MasterData;

use App\Models\MasterData\Faculty;
use Spatie\LaravelData\Data;

class FacultyFormData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?Faculty $current = null): self
    {
        return new self(
            code: $input['code'] ?? $current?->code,
            name: $input['name'] ?? $current?->name,
        );
    }
}
