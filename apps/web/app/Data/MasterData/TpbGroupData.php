<?php

namespace App\Data\MasterData;

use App\Data\BaseData;
use App\Enums\MasterData\TpbCohort;
use App\Extensions\Data\FromPolicy;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * class_numbers = classes of the offering in this group (1 = A). The slot is not stored here:
 * it comes from the active TPB scheduling run.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class TpbGroupData extends BaseData
{
    /**
     * @param  list<TpbParticipantData>  $participants
     * @param  list<int>  $classNumbers
     */
    public function __construct(
        public int $id,
        public int $tpbOfferingId,
        public string $code,
        public TpbCohort $cohort,
        #[DataCollectionOf(TpbParticipantData::class)]
        public array $participants,
        public array $classNumbers,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function relations(): array
    {
        return ['studyPrograms', 'courseLecturers:id,tpb_group_id,class_number'];
    }
}
