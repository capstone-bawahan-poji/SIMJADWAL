<?php

namespace Tests\Concerns;

use App\Enums\Scheduling\RunStatus;
use App\Enums\Scheduling\SubmissionStatus;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Study programs are scheduled in the cells their faculty gives them. These helpers set up the
 * state a test needs without going through the API.
 */
trait BuildsProgramSchedules
{
    /**
     * @param  list<array{0: TimeSlot|int, 1: Room|int}>  $cells
     */
    protected function giveCells(StudyProgram $program, string $period, array $cells): void
    {
        $by = User::factory()->facultyAdmin($program->faculty)->create();
        foreach ($cells as [$slot, $room]) {
            RoomSlotAllocation::query()->create([
                'period' => $period,
                'faculty_id' => $program->faculty_id,
                'study_program_id' => $program->id,
                'time_slot_id' => $slot instanceof TimeSlot ? $slot->id : $slot,
                'room_id' => $room instanceof Room ? $room->id : $room,
                'allocated_by' => $by->id,
            ]);
        }
    }

    /**
     * @param  list<array<string, int|null>>  $details  schedule_details rows without scheduling_run_id
     */
    protected function programRun(StudyProgram $program, string $period, array $details = [], bool $active = true, bool $published = false): SchedulingRun
    {
        $user = User::factory()->studyProgramAdmin($program)->create();
        $run = SchedulingRun::query()->create([
            'client_run_id' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $program->faculty_id,
            'study_program_id' => $program->id,
            'period' => $period,
            'parameter_snapshot' => ['weights' => []],
            'status' => RunStatus::DONE,
            'best_fitness' => 500,
            'max_fitness' => 500,
            'is_active' => $active,
            'triggered_by' => $user->id,
            'published_by' => $published ? $user->id : null,
            'published_at' => $published ? now() : null,
        ]);
        foreach ($details as $detail) {
            ScheduleDetail::query()->create(['scheduling_run_id' => $run->id, 'meeting_number' => 1, ...$detail]);
        }
        if ($active) {
            ProgramScheduleSubmission::query()->updateOrCreate(
                ['study_program_id' => $program->id, 'period' => $period],
                ['scheduling_run_id' => $run->id, 'status' => $published ? SubmissionStatus::PUBLISHED : SubmissionStatus::DRAFT],
            );
        }

        return $run;
    }

    protected function setSubmission(StudyProgram $program, string $period, SubmissionStatus $status): void
    {
        ProgramScheduleSubmission::query()->updateOrCreate(
            ['study_program_id' => $program->id, 'period' => $period],
            ['status' => $status],
        );
    }
}
