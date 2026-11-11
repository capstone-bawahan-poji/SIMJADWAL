<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\SubmissionStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\SchedulePublication;

/**
 * Read side of the review flow, shared by the services that must stop once a program handed its
 * schedule in or its faculty published. A program without a submission row is a draft.
 */
readonly class ProgramScheduleState
{
    public function status(int $studyProgramId, string $period): SubmissionStatus
    {
        return $this->statuses([$studyProgramId], $period)[$studyProgramId];
    }

    /**
     * @param  list<int>  $studyProgramIds
     * @return array<int, SubmissionStatus>
     */
    public function statuses(array $studyProgramIds, string $period): array
    {
        $found = ProgramScheduleSubmission::query()
            ->whereIn('study_program_id', $studyProgramIds)
            ->where('period', $period)
            ->pluck('status', 'study_program_id')
            ->all();

        return collect($studyProgramIds)
            ->mapWithKeys(fn (int $id) => [$id => $found[$id] ?? SubmissionStatus::DRAFT])
            ->all();
    }

    public function isFacultyPublished(int $facultyId, string $period): bool
    {
        return SchedulePublication::query()->where('faculty_id', $facultyId)->where('period', $period)->exists();
    }

    /**
     * The program may still run the GA, activate a run and move meetings.
     */
    public function assertEditable(StudyProgram $studyProgram, string $period): void
    {
        if ($this->isFacultyPublished($studyProgram->faculty_id, $period)) {
            throw ApiException::invalidState(__('The schedule of :period is published and final.', ['period' => Period::label($period)]));
        }

        if (! $this->status($studyProgram->id, $period)->isEditable()) {
            throw ApiException::invalidState(__('The schedule was handed to the faculty. Withdraw it, or wait until the faculty returns it.'));
        }
    }

    public function assertFacultyNotPublished(int $facultyId, string $period): void
    {
        if ($this->isFacultyPublished($facultyId, $period)) {
            throw ApiException::invalidState(__('The schedule of :period is published and final.', ['period' => Period::label($period)]));
        }
    }
}
