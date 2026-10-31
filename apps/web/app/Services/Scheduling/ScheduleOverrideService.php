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
 * Manual override of one gene in the active schedule. Only time_slot_id and room_id change
 * (DATABASE.md §2 rule 9), and the new place must break no hard constraint (ScheduleSlotChecker).
 */
readonly class ScheduleOverrideService
{
    public function __construct(
        private DatabaseManager $db,
        private ScheduleSlotCheckerFactory $checkers,
        private ChangeRecorder $changes,
    ) {}

    /**
     * Every slot with whether the gene may move there, and the free rooms in it.
     *
     * @return array{current: array{time_slot_id: int, room_id: int|null}, time_slots: list<array<string, mixed>>}
     */
    public function options(ScheduleDetail $detail): array
    {
        $this->assertActive($detail);
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
        ];
    }

    public function override(User $actor, ScheduleDetail $detail, int $timeSlotId, ?int $roomId): ScheduleDetail
    {
        $this->assertActive($detail);
        $run = $detail->schedulingRun;

        $this->db->transaction(function () use ($actor, $detail, $run, $timeSlotId, $roomId) {
            // Serialises overrides in the period so two admins cannot take the same free slot.
            SchedulingRun::query()->active()->where('period', $run->period)->lockForUpdate()->get();

            $checker = $this->checkers->for($detail);
            $room = $roomId !== null ? Room::query()->findOrFail($roomId) : null;
            $errors = array_filter([
                'room_id' => $room !== null ? $checker->roomErrors($timeSlotId, $room) : [],
                'time_slot_id' => $checker->slotErrors($timeSlotId),
            ]);
            if ($errors !== []) {
                throw ApiException::validation($errors);
            }

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
        });

        activity('scheduling')->causedBy($actor)->performedOn($run)->event('schedule_detail.overridden')
            ->withProperties(['schedule_detail_id' => $detail->id, 'time_slot_id' => $timeSlotId, 'room_id' => $roomId])
            ->log(__('Overrode schedule entry :id of run :run', ['id' => $detail->id, 'run' => $run->id]));

        return $detail;
    }

    private function assertActive(ScheduleDetail $detail): void
    {
        if (! $detail->schedulingRun->is_active) {
            throw ApiException::invalidState(__('Only the active schedule can be overridden.'));
        }
    }
}