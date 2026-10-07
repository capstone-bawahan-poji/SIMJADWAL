<?php

namespace App\Services\Constraint;

use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;

/**
 * Audit trail of constraint changes: weights, lecturer preferences and the submission flow.
 * Rows share the activity_logs table with account logs under log name "constraint".
 */
readonly class ConstraintActivityLogService
{
    public const CONSTRAINT_WEIGHT_CHANGED = 'constraint.weight_changed';

    public const PREFERENCES_REPLACED = 'preference.replaced';

    public const CONSTRAINTS_SUBMITTED = 'constraint.submitted';

    public const CONSTRAINTS_ACCEPTED = 'constraint.accepted';

    public const CONSTRAINTS_RETURNED = 'constraint.returned';

    private const LOG_NAME = 'constraint';

    public function constraintWeightChanged(ConstraintType $constraintType, string $oldWeight): void
    {
        if ((float) $oldWeight === (float) $constraintType->weight) {
            return;
        }

        activity(self::LOG_NAME)
            ->performedOn($constraintType)
            ->event(self::CONSTRAINT_WEIGHT_CHANGED)
            ->withChanges(['old' => ['weight' => $oldWeight], 'attributes' => ['weight' => (string) $constraintType->weight]])
            ->log(__('Changed weight of :code', ['code' => $constraintType->code->value]));
    }

    /**
     * @param  list<array{time_slot_id: int, type: string}>  $before
     * @param  list<array{time_slot_id: int, type: string}>  $after
     */
    public function preferencesReplaced(Lecturer $lecturer, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        activity(self::LOG_NAME)
            ->performedOn($lecturer)
            ->event(self::PREFERENCES_REPLACED)
            ->withChanges(['old' => ['preferences' => $before], 'attributes' => ['preferences' => $after]])
            ->log(__('Updated preferences of :name', ['name' => $lecturer->name]));
    }

    public function constraintsSubmitted(StudyProgram $studyProgram): void
    {
        $this->logSubmission($studyProgram, self::CONSTRAINTS_SUBMITTED, __('Submitted constraints of :name', ['name' => $studyProgram->name]));
    }

    public function constraintsAccepted(StudyProgram $studyProgram): void
    {
        $this->logSubmission($studyProgram, self::CONSTRAINTS_ACCEPTED, __('Accepted constraints of :name', ['name' => $studyProgram->name]));
    }

    public function constraintsReturned(StudyProgram $studyProgram): void
    {
        $this->logSubmission($studyProgram, self::CONSTRAINTS_RETURNED, __('Returned constraints of :name', ['name' => $studyProgram->name]), [
            'note' => $studyProgram->constraint_return_note,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logSubmission(StudyProgram $studyProgram, string $event, string $description, array $properties = []): void
    {
        activity(self::LOG_NAME)
            ->performedOn($studyProgram)
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }
}
