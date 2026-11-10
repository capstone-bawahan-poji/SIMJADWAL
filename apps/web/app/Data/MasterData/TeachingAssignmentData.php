<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One class of a course and its lecturer (a course_lecturers row). class_label is A, B, ...
 * tpb_offering_id and tpb_group_id are filled for TPB classes only. A TPB class may have no lecturer yet.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TeachingAssignmentData extends BaseData
{
    public function __construct(
        public int $id,
        public int $courseId,
        public int $classNumber,
        public string $classLabel,
        public ?int $lecturerId,
        public ?int $tpbOfferingId,
        public ?int $tpbGroupId,
        public CourseSummaryData $course,
        public ?LecturerSummaryData $lecturer,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function relations(): array
    {
        return ['course.studyProgram', 'lecturer'];
    }
}
