<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * faculty_id NULL (is_shared) = room shared across faculties.
 * is_in_use: the room appears in an active scheduling run. Next step: status per day and session.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class RoomData extends BaseData
{
    #[Computed]
    public bool $isShared;

    public function __construct(
        public int $id,
        public ?int $facultyId,
        public string $code,
        public ?string $name,
        public ?string $building,
        public ?int $floor,
        public int $capacity,
        public ?FacultySummaryData $faculty,
        public bool $isInUse,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {
        $this->isShared = $facultyId === null;
    }

    public static function relations(): array
    {
        return ['faculty'];
    }

    public static function existRelations(): array
    {
        return ['activeScheduleDetails as is_in_use'];
    }
}
