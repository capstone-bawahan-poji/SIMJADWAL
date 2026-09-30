<?php

namespace App\Data\MasterData;

use App\Models\MasterData\Lecturer;
use Spatie\LaravelData\Data;

class LecturerFormData extends Data
{
    public function __construct(
        public int $studyProgramId,
        public string $code,
        public string $name,
        public ?string $title,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?Lecturer $current = null): self
    {
        return new self(
            studyProgramId: (int) ($input['study_program_id'] ?? $current?->study_program_id),
            code: $input['code'] ?? $current?->code,
            name: $input['name'] ?? $current?->name,
            title: array_key_exists('title', $input) ? $input['title'] : $current?->title,
        );
    }
}
