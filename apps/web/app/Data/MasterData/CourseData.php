<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * course_lecturers_count = classes that already have a lecturer, out of parallel_class_count.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class CourseData extends BaseData
{
    public function __construct(
        public int $id,
        public ?int $studyProgramId,
        public bool $isTpb,
        public string $code,
        public string $name,
        public int $sks,
        public int $semester,
        public int $parallelClassCount,
        public int $classCapacity,
        public ?StudyProgramSummaryData $studyProgram,
        public int $courseLecturersCount,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function relations(): array
    {
        return ['studyProgram'];
    }

    public static function countRelations(): array
    {
        return ['courseLecturers'];
    }
}
