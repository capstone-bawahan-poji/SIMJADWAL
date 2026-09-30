<?php

namespace App\Data\MasterData;

use App\Models\MasterData\Course;
use Spatie\LaravelData\Data;

/**
 * study_program_id is null exactly when is_tpb is true.
 */
class CourseFormData extends Data
{
    public function __construct(
        public ?int $studyProgramId,
        public bool $isTpb,
        public string $code,
        public string $name,
        public int $sks,
        public int $semester,
        public int $parallelClassCount,
        public int $classCapacity,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?Course $current = null): self
    {
        $isTpb = (bool) ($input['is_tpb'] ?? $current?->is_tpb ?? false);

        return new self(
            studyProgramId: $isTpb ? null : (int) ($input['study_program_id'] ?? $current?->study_program_id),
            isTpb: $isTpb,
            code: $input['code'] ?? $current?->code,
            name: $input['name'] ?? $current?->name,
            sks: (int) ($input['sks'] ?? $current?->sks),
            semester: (int) ($input['semester'] ?? $current?->semester),
            parallelClassCount: (int) ($input['parallel_class_count'] ?? $current?->parallel_class_count ?? 1),
            classCapacity: (int) ($input['class_capacity'] ?? $current?->class_capacity),
        );
    }
}
