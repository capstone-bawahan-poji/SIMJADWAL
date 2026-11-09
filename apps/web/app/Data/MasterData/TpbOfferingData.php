<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * course_lecturers_count = classes already placed in a group, assigned_classes_count = those with a lecturer.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TpbOfferingData extends BaseData
{
    public function __construct(
        public int $id,
        public int $courseId,
        public string $period,
        public int $parallelClassCount,
        public int $classCapacity,
        public CourseSummaryData $course,
        public int $groupsCount,
        public int $courseLecturersCount,
        public int $assignedClassesCount,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function relations(): array
    {
        return ['course'];
    }

    public static function countRelations(): array
    {
        return ['groups', 'courseLecturers', 'assignedClasses'];
    }
}
