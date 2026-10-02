<?php

namespace App\Data\MasterData;

use App\Models\MasterData\TpbGroup;
use Spatie\LaravelData\Data;

class TpbGroupFormData extends Data
{
    /**
     * @param  list<int>  $studyProgramIds  participating programs, from any faculty
     */
    public function __construct(
        public int $courseId,
        public string $code,
        public ?int $timeSlotId,
        public array $studyProgramIds,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?TpbGroup $current = null): self
    {
        return new self(
            courseId: (int) ($input['course_id'] ?? $current?->course_id),
            code: $input['code'] ?? $current?->code,
            timeSlotId: array_key_exists('time_slot_id', $input)
                ? ($input['time_slot_id'] === null ? null : (int) $input['time_slot_id'])
                : $current?->time_slot_id,
            studyProgramIds: array_map('intval', $input['study_program_ids'] ?? $current?->studyPrograms->modelKeys() ?? []),
        );
    }
}
