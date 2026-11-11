<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\SlotRequestStatus;
use App\Enums\Scheduling\SubmissionStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\SlotRequest;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * POS steps 8-9. The program hands its active run to the faculty (submitted), may take it back
 * before review (draft), and the faculty accepts it or returns it with a note (returned). Accepted
 * programs are published together by SchedulePublicationService.
 *
 *   draft/returned --submit--> submitted --accept--> accepted --publish--> published
 *   submitted --withdraw--> draft, submitted/accepted --return--> returned
 */
readonly class ProgramScheduleSubmissionService
{
    public function __construct(
        private DatabaseManager $db,
        private ProgramScheduleState $state,
        private ProgramRunValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(StudyProgram $studyProgram, string $period): array
    {
        $submission = $this->find($studyProgram, $period);
        $run = $submission?->schedulingRun;
        $status = $submission->status ?? SubmissionStatus::DRAFT;

        return [
            'period' => $period,
            'study_program' => ['id' => $studyProgram->id, 'code' => $studyProgram->code, 'name' => $studyProgram->name],
            'status' => $status->value,
            'faculty_published' => $this->state->isFacultyPublished($studyProgram->faculty_id, $period),
            'scheduling_run' => $run === null ? null : [
                'id' => $run->id,
                'is_active' => $run->is_active,
                'best_fitness' => $run->best_fitness !== null ? (float) $run->best_fitness : null,
                'max_fitness' => $run->max_fitness !== null ? (float) $run->max_fitness : null,
                'finished_at' => $run->finished_at?->toIso8601String(),
            ],
            // What would stop a submit now; empty for a run already handed in.
            'problems' => $run !== null && $run->is_active && $status->isEditable() ? array_filter($this->validator->problems($run)) : [],
            'cells_count' => RoomSlotAllocation::query()->where('study_program_id', $studyProgram->id)->where('period', $period)->count(),
            'submitted_at' => $submission?->submitted_at?->toIso8601String(),
            'reviewed_at' => $submission?->reviewed_at?->toIso8601String(),
            'return_note' => $submission?->return_note,
        ];
    }

    /**
     * Status of every program of the faculty, for the review page.
     *
     * @return list<array<string, mixed>>
     */
    public function forFaculty(Faculty $faculty, string $period): array
    {
        $programs = StudyProgram::query()->where('faculty_id', $faculty->id)->orderBy('code')->get();
        $submissions = ProgramScheduleSubmission::query()->with('schedulingRun')
            ->whereIn('study_program_id', $programs->pluck('id'))->where('period', $period)
            ->get()->keyBy('study_program_id');
        $cells = RoomSlotAllocation::query()->where('faculty_id', $faculty->id)->where('period', $period)
            ->selectRaw('study_program_id, count(*) as aggregate')->groupBy('study_program_id')->pluck('aggregate', 'study_program_id');
        $pending = SlotRequest::query()->whereIn('study_program_id', $programs->pluck('id'))->where('period', $period)
            ->where('status', SlotRequestStatus::PENDING)->pluck('study_program_id')->flip();

        return $programs->map(function (StudyProgram $program) use ($submissions, $cells, $pending) {
            $submission = $submissions[$program->id] ?? null;
            $run = $submission?->schedulingRun;

            return [
                'study_program' => ['id' => $program->id, 'code' => $program->code, 'name' => $program->name],
                'status' => ($submission->status ?? SubmissionStatus::DRAFT)->value,
                'scheduling_run_id' => $run?->id,
                'best_fitness' => $run?->best_fitness !== null ? (float) $run->best_fitness : null,
                'max_fitness' => $run?->max_fitness !== null ? (float) $run->max_fitness : null,
                'cells_count' => (int) ($cells[$program->id] ?? 0),
                'has_pending_slot_request' => isset($pending[$program->id]),
                'submitted_at' => $submission?->submitted_at?->toIso8601String(),
                'reviewed_at' => $submission?->reviewed_at?->toIso8601String(),
                'return_note' => $submission?->return_note,
            ];
        })->values()->all();
    }

    public function submit(User $actor, StudyProgram $studyProgram, string $period): array
    {
        $this->state->assertEditable($studyProgram, $period);

        $this->db->transaction(function () use ($actor, $studyProgram, $period) {
            $submission = $this->lock($studyProgram, $period);
            $run = $submission?->schedulingRun;
            if ($run === null || ! $run->is_active) {
                throw ApiException::invalidState(__('Activate a run before handing the schedule to the faculty.'));
            }
            $this->validator->assertValid($run);

            $submission->update([
                'status' => SubmissionStatus::SUBMITTED,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
        });

        return $this->logged($actor, $studyProgram, $period, 'submitted', __('Handed the schedule of :program to the faculty', ['program' => $studyProgram->code]));
    }

    public function withdraw(User $actor, StudyProgram $studyProgram, string $period): array
    {
        $this->transition($studyProgram, $period, [SubmissionStatus::SUBMITTED], ['status' => SubmissionStatus::DRAFT]);

        return $this->logged($actor, $studyProgram, $period, 'withdrawn', __('Took back the schedule of :program', ['program' => $studyProgram->code]));
    }

    public function accept(User $actor, StudyProgram $studyProgram, string $period): array
    {
        $this->transition($studyProgram, $period, [SubmissionStatus::SUBMITTED], [
            'status' => SubmissionStatus::ACCEPTED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'return_note' => null,
        ]);

        return $this->logged($actor, $studyProgram, $period, 'accepted', __('Accepted the schedule of :program', ['program' => $studyProgram->code]));
    }

    public function return(User $actor, StudyProgram $studyProgram, string $period, string $note): array
    {
        $this->transition($studyProgram, $period, [SubmissionStatus::SUBMITTED, SubmissionStatus::ACCEPTED], [
            'status' => SubmissionStatus::RETURNED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'return_note' => $note,
        ]);

        return $this->logged($actor, $studyProgram, $period, 'returned', __('Returned the schedule of :program', ['program' => $studyProgram->code]));
    }

    /**
     * @param  list<SubmissionStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function transition(StudyProgram $studyProgram, string $period, array $from, array $attributes): void
    {
        $this->state->assertFacultyNotPublished($studyProgram->faculty_id, $period);

        $this->db->transaction(function () use ($studyProgram, $period, $from, $attributes) {
            $submission = $this->lock($studyProgram, $period);
            if ($submission === null || ! in_array($submission->status, $from, true)) {
                throw ApiException::invalidState(__('The schedule is :status, so this step does not apply.', [
                    'status' => ($submission->status ?? SubmissionStatus::DRAFT)->label(),
                ]));
            }
            $submission->update($attributes);
        });
    }

    private function find(StudyProgram $studyProgram, string $period): ?ProgramScheduleSubmission
    {
        return ProgramScheduleSubmission::query()->with('schedulingRun')
            ->where('study_program_id', $studyProgram->id)->where('period', $period)->first();
    }

    private function lock(StudyProgram $studyProgram, string $period): ?ProgramScheduleSubmission
    {
        return ProgramScheduleSubmission::query()->with('schedulingRun')
            ->where('study_program_id', $studyProgram->id)->where('period', $period)
            ->lockForUpdate()->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function logged(User $actor, StudyProgram $studyProgram, string $period, string $event, string $message): array
    {
        activity('scheduling')->causedBy($actor)->event("program_schedule.{$event}")
            ->withProperties(['study_program_id' => $studyProgram->id, 'period' => $period])
            ->log($message);

        return $this->show($studyProgram, $period);
    }
}
