<?php

namespace App\Data\MasterData;

use App\Models\MasterData\Department;
use Spatie\LaravelData\Data;

class DepartmentFormData extends Data
{
    public function __construct(
        public int $facultyId,
        public string $code,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?Department $current = null): self
    {
        return new self(
            facultyId: (int) ($input['faculty_id'] ?? $current?->faculty_id),
            code: $input['code'] ?? $current?->code,
            name: $input['name'] ?? $current?->name,
        );
    }
}
