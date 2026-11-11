<?php

namespace App\Services\Scheduling;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\ViolationLog;
use App\Models\User;
use App\Services\Shared\ChangeRecorder;
use Illuminate\Database\DatabaseManager;

/**
 * Manual changes to the active run of a study program while its schedule is still a draft: move
 * one meeting to a free cell of the program, or swap the cells of two meetings when no cell is
 * free. Only time_slot_id and room_id change (DATABASE.md §2 rule 9), and the new places must
 * break no hard constraint (ScheduleSlotChecker). Once handed to the faculty nothing moves; a
 * published schedule is final. TPB runs move per group block in TpbOverrideService.
 */
readonly class ScheduleOverrideService
{
    public function __construct(
        private DatabaseManager $db,
        private ScheduleSlotCheckerFactory $checkers,
        private ProgramScheduleState $state,
        private ChangeRecorder $changes,
    ) {}

    /**
     * Every slot with whether the meeting may move there and the free cells in it, plus the
     * meetings of the run it may swap with.
     *
     * @return array{current: array{time_slot_id: int, room_id: int|null}, time_slots: list<array<string, mixed>>, swap_candidates: list<array<string, mixed>>}
     */
    public function options(ScheduleDetail $detail): array
    {
        $this->assertEditable($detail);
        $this->checkers->forget();
        $checker = $this->checkers->for($detail);

        return [
            'current' => ['time_slot_id' => $detail->time_slot_id, 'room_id' => $detail->room_id],
            'time_slots' => TimeSlot::query()->orderBy('day')->orderBy('session')->get()
                ->map(function (TimeSlot $slot) use ($checker) {
                    $reasons = $checker->slotErrors($slot->id);

                    return [
                        'id' => $slot->id,
                        'day' => $slot->day->value,
                        'session' => $slot->session,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'available' => $reasons === [],
                        'reasons' => $reasons,
                        'rooms' => $reasons === []
                            ? $checker->freeRooms($slot->id)
                                ->map(fn (Room $room) => ['id' => $room->id, 'code' => $room->code, 'name' => $room->name, 'capacity' => $room->capacity])
                                ->all()
                            : [],
                    ];
                })->values()->all(),
            'swap_candidates' => $this->checkers->genesOf($detail->schedulingRun)
                ->reject(fn (ScheduleDetail $other) => $other->id === $detail->id || $other->room_id === null || $detail->room_id === null)
                ->filter(fn (ScheduleDetail $other) => $this->swapErrors($detail, $other) === [])
                ->map(fn (ScheduleDetail $other) => [
                    'schedule_detail_id' => $other->id,
                    'course' => ['id' => $other->course_id, 'name' => $other->course->name],
                    'class_number' => $other->class_number,
                    'meeting_number' => $other->meeting_number,
                    'time_slot_id' => $other->time_slot_id,
                    'room_id' => $other->room_id,
                    'room_code' => $other->room?->code,
                ])->values()->all(),
        ];
    }

    public function override(User $actor, ScheduleDetail $detail, int $timeSlotId, int $roomId, ?string $reason = null): ScheduleDetail
    {
        $this->assertEditable($detail);
        $run = $detail->schedulingRun;

        $this->db->transaction(function () use ($actor, $detail, $run, $timeSlotId, $roomId) {
            $this->lockPeriod($run);
            $checker = $this->checkers->for($detail);
            $room = Room::query()->findOrFail($roomId);
            $errors = array_filter([
                'room_id' => $checker->roomErrors($timeSlotId, $room),
                'time_slot_id' => $checker->slotErrors($timeSlotId),
            ]);
            if ($errors !== []) {
                throw ApiException::validation($errors);
            }

            $this->move($actor, $detail, $timeSlotId, $roomId);
        });

        $this->log($actor, $run, 'schedule_detail.overridden', ['schedule_detail_id' => $detail->id, 'time_slot_id' => $timeSlotId, 'room_id' => $roomId, 'reason' => $reason],
            __('Overrode schedule entry :id of run :run', ['id' => $detail->id, 'run' => $run->id]));

        return $detail;
    }

    /**
     * Swaps the cells (slot and room) of two meetings of the same run.
     *
     * @return array{0: ScheduleDetail, 1: ScheduleDetail}
     */
    public function swap(User $actor, ScheduleDetail $a, ScheduleDetail $b, ?string $reason = null): array
    {
        if ($a->id === $b->id || $a->scheduling_run_id !== $b->scheduling_run_id) {
            throw ApiException::validation(['b' => [__('Pick another meeting of the same schedule.')]]);
        }
        $this->assertEditable($a);
        $run = $a->schedulingRun;

        $this->db->transaction(function () use ($actor, $a, $b, $run) {
            $this->lockPeriod($run);
            if ($a->room_id === null || $b->room_id === null) {
                throw ApiException::validation(['b' => [__('Both meetings need a room to swap.')]]);
            }
            $errors = $this->swapErrors($a, $b);
            if ($errors !== []) {
                throw ApiException::validation($errors);
            }

            [$slotA, $roomA, $slotB, $roomB] = [$a->time_slot_id, $a->room_id, $b->time_slot_id, $b->room_id];
            // uq_schedule_details_cell is checked per statement, so A leaves its cell first.
            $a->update(['room_id' => null]);
            $this->move($actor, $b, $slotA, $roomA);
            $this->move($actor, $a, $slotB, $roomB);
        });

        $this->log($actor, $run, 'schedule_detail.swapped', ['schedule_detail_ids' => [$a->id, $b->id], 'reason' => $reason],
            __('Swapped schedule entries :a and :b of run :run', ['a' => $a->id, 'b' => $b->id, 'run' => $run->id]));

        return [$a, $b];
    }

    /**
     * Errors of putting A in B's cell and B in A's cell, each checked as if the other were gone.
     *
     * @return array<string, list<string>>
     */
    private function swapErrors(ScheduleDetail $a, ScheduleDetail $b): array
    {
        $genes = $this->checkers->genesOf($a->schedulingRun)->keyBy('id');
        $roomA = $genes->get($a->id)?->room ?? Room::query()->findOrFail($a->room_id);
        $roomB = $genes->get($b->id)?->room ?? Room::query()->findOrFail($b->room_id);

        return array_filter([
            'a' => $this->checkers->for($a, [$b->id])->placeErrors($b->time_slot_id, $roomB),
            'b' => $this->checkers->for($b, [$a->id])->placeErrors($a->time_slot_id, $roomA),
        ]);
    }

    private function move(User $actor, ScheduleDetail $detail, int $timeSlotId, int $roomId): void
    {
        $detail->update([
            'time_slot_id' => $timeSlotId,
            'room_id' => $roomId,
            'is_overridden' => true,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
        ]);
        // Penalties were computed for the old place and no longer hold.
        ViolationLog::query()->where('schedule_detail_id', $detail->id)->delete();
        $this->changes->record('schedule_details', $detail->id);
        $this->changes->record('violation_logs');
    }

    /**
     * Serialises changes in the period so two admins cannot take the same free cell or lecturer slot.
     */
    private function lockPeriod(SchedulingRun $run): void
    {
        SchedulingRun::query()->active()->where('period', $run->period)->lockForUpdate()->get();
        $this->checkers->forget();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(User $actor, SchedulingRun $run, string $event, array $properties, string $message): void
    {
        activity('scheduling')->causedBy($actor)->performedOn($run)->event($event)->withProperties($properties)->log($message);
    }

    private function assertEditable(ScheduleDetail $detail): void
    {
        $run = $detail->schedulingRun;
        if (! $run->is_active) {
            throw ApiException::invalidState(__('Only the active schedule can be overridden.'));
        }
        if ($run->isTpb()) {
            throw ApiException::invalidState(__('A TPB meeting moves together with its group. Use the TPB block override instead.'));
        }
        if ($run->study_program_id === null) {
            throw ApiException::invalidState(__('Faculty-wide runs are no longer used. Each study program runs its own GA.'));
        }
        $this->state->assertEditable($run->studyProgram, $run->period);
    }
}
