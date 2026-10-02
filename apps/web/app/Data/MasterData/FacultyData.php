<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class FacultyData extends BaseData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public int $departmentsCount,
        public int $studyProgramsCount,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function countRelations(): array
    {
        return ['departments', 'studyPrograms'];
    }
}
