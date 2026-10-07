<?php

namespace App\Data\MasterData;

use App\Data\Account\UserSummaryData;
use App\Data\BaseData;
use App\Enums\Constraint\ConstraintStatus;
use App\Extensions\Data\FromPolicy;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class StudyProgramData extends BaseData
{
    public function __construct(
        public int $id,
        public int $facultyId,
        public ?int $departmentId,
        public string $code,
        public string $name,
        public ConstraintStatus $constraintStatus,
        public ?CarbonImmutable $constraintSubmittedAt,
        public ?CarbonImmutable $constraintReviewedAt,
        public ?string $constraintReturnNote,
        public FacultySummaryData $faculty,
        public ?DepartmentSummaryData $department,
        public ?UserSummaryData $coordinator,
        #[FromPolicy('update')]
        public bool $canUpdate = false,
        #[FromPolicy('delete')]
        public bool $canDelete = false,
    ) {}

    public static function relations(): array
    {
        return ['faculty', 'department', 'coordinator.lecturer'];
    }
}
