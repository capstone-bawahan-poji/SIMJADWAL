<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * course_lecturers_count = classes placed in this group. time_slot is null until the TPB admin places the group.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TpbGroupData extends BaseData
{
    /**
     * @param  list<StudyProgramSummaryData>  $studyPrograms
     */
    public function __construct(
        public int $id,
        public int $courseId,
        public string $code,
        public ?int $timeSlotId,
        public CourseSummaryData $course,
        public ?TimeSlotSummaryData $timeSlot,
        #[DataCollectionOf(StudyProgramSummaryData::class)]
        public array $studyPrograms,
        public int $courseLecturersCount,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function relations(): array
    {
        return ['course', 'timeSlot', 'studyPrograms'];
    }

    public static function countRelations(): array
    {
        return ['courseLecturers'];
    }
}
