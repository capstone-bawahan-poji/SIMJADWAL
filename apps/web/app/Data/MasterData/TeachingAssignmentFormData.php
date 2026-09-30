<?php

namespace App\Data\MasterData;

use App\Models\MasterData\CourseLecturer;
use Spatie\LaravelData\Data;

/**
 * tpb_group_id and room_id apply to TPB courses only.
 */
class TeachingAssignmentFormData extends Data
{
    public function __construct(
        public int $courseId,
        public int $classNumber,
        public int $lecturerId,
        public ?int $tpbGroupId,
        public ?int $roomId,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request; on PATCH absent fields keep $current values
     */
    public static function fromInput(array $input, ?CourseLecturer $current = null): self
    {
        $optional = fn (string $key, ?int $currentValue) => array_key_exists($key, $input)
            ? ($input[$key] === null ? null : (int) $input[$key])
            : $currentValue;

        return new self(
            courseId: (int) ($input['course_id'] ?? $current?->course_id),
            classNumber: (int) ($input['class_number'] ?? $current?->class_number),
            lecturerId: (int) ($input['lecturer_id'] ?? $current?->lecturer_id),
            tpbGroupId: $optional('tpb_group_id', $current?->tpb_group_id),
            roomId: $optional('room_id', $current?->room_id),
        );
    }
}
