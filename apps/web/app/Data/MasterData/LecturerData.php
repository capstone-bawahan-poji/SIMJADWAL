<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class LecturerData extends BaseData
{
    public function __construct(
        public int $id,
        public int $studyProgramId,
        public ?int $userId,
        public string $code,
        public string $name,
        public ?string $title,
        public StudyProgramSummaryData $studyProgram,
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
