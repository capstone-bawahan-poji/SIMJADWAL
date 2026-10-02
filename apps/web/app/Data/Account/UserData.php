<?php

namespace App\Data\Account;

use App\Data\BaseData;
use App\Data\MasterData\FacultySummaryData;
use App\Data\MasterData\LecturerSummaryData;
use App\Data\MasterData\StudyProgramSummaryData;
use App\Enums\Account\Role;
use App\Extensions\Data\FromPolicy;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * faculty and study_program are the account's own scope. A lecturer account has neither;
 * its homebase is lecturer.study_program_id.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class UserData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $identityNumber,
        public ?Role $role,
        public ?int $facultyId,
        public ?int $studyProgramId,
        public ?int $lecturerId,
        public ?FacultySummaryData $faculty,
        public ?StudyProgramSummaryData $studyProgram,
        public ?LecturerSummaryData $lecturer,
        public bool $isActive,
        public ?CarbonImmutable $createdAt,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('updateStatus')]
        public bool $canUpdateStatus = false,
    ) {}

    public static function relations(): array
    {
        return ['roles', 'faculty', 'studyProgram', 'lecturer'];
    }
}
