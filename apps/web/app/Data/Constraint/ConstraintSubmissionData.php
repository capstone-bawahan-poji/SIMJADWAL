<?php

namespace App\Data\Constraint;

use App\Data\BaseData;
use App\Data\MasterData\FacultySummaryData;
use App\Enums\Constraint\ConstraintStatus;
use App\Extensions\Data\FromPolicy;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One study program and how far its constraints are. Counts come from
 * ConstraintSubmissionService::query(): incomplete_courses_count = courses with fewer
 * lecturers than parallel classes.
 */
#[TypeScript]
#[MapName(SnakeCaseMapper::class)]
class ConstraintSubmissionData extends BaseData
{
    #[Computed]
    public bool $isLocked;

    public function __construct(
        public int $id,
        public int $facultyId,
        public string $code,
        public string $name,
        public ConstraintStatus $constraintStatus,
        public ?CarbonImmutable $constraintSubmittedAt,
        public ?CarbonImmutable $constraintReviewedAt,
        public ?string $constraintReturnNote,
        public FacultySummaryData $faculty,
        public int $lecturersCount,
        public int $lecturersWithPreferencesCount,
        public int $coursesCount,
        public int $incompleteCoursesCount,
        #[FromPolicy('submitConstraints')]
        public bool $canSubmit = false,
        #[FromPolicy('reviewConstraints')]
        public bool $canReview = false,
    ) {
        $this->isLocked = $constraintStatus->locks();
    }

    public static function relations(): array
    {
        return ['faculty'];
    }
}
