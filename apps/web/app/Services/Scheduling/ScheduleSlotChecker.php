<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Support\Collection;

/**
 * Hard constraints for moving one gene of an active study program run, checked in memory. Built by
 * ScheduleSlotCheckerFactory; the options, move and swap endpoints share it.
 *
 *   - slot: lecturer free (every active run of the period + TPB), class free, TPB program block,
 *   - room: one of the program's cells in that slot, big enough, free in every active run, not TPB's.
 */
readonly class ScheduleSlotChecker
{
    /**
     * @param  array<int, ScheduleDetail>  $cohortAt  slot => gene of the same program, semester and class
     * @param  list<int>  $blockedForProgram
     * @param  list<int>  $blockedForLecturer
     * @param  array<int, list<int>>  $blockedRooms  room_id => slots
     * @param  array<int, Collection<int, Room>>  $cellRooms  slot => the program's rooms in it, smallest first
     */
    public function __construct(
        private ScheduleDetail $detail,
        private ActiveScheduleOccupancy $occupancy,
        private array $cohortAt,
        private array $blockedForProgram,
        private array $blockedForLecturer,
        private array $blockedRooms,
        private array $cellRooms,
    ) {}

    /**
     * Why the gene cannot move to the slot, whatever the room. Empty = the slot is free.
     *
     * @return list<string>
     */
    public function slotErrors(int $slotId): array
    {
        $errors = [];

        if ($clash = $this->occupancy->lecturerClash($slotId, $this->detail->lecturer_id)) {
            $errors[] = __('The lecturer already teaches :course :class in this slot.', self::describe($clash));
        } elseif (in_array($slotId, $this->blockedForLecturer, true)) {
            $errors[] = __('The lecturer teaches a TPB class in this slot.');
        }
        if ($clash = $this->cohortAt[$slotId] ?? null) {
            $errors[] = __('The class already has :course :class in this slot.', self::describe($clash));
        }
        if (in_array($slotId, $this->blockedForProgram, true)) {
            $errors[] = __('The slot is reserved for a TPB class of this semester.');
        }

        return $errors;
    }

    /**
     * Why the room cannot be used in the slot. Empty = the room is the program's, fits and is free.
     *
     * @return list<string>
     */
    public function roomErrors(int $slotId, Room $room): array
    {
        if (! ($this->cellRooms[$slotId] ?? collect())->contains('id', $room->id)) {
            return [__('The room is not one of the program\'s room slots at this time.')];
        }
        if ($room->capacity < $this->detail->course->class_capacity) {
            return [__('The room is too small for the class.')];
        }
        if ($clash = $this->occupancy->roomClash($slotId, $room->id)) {
            return [__('The room is already used by :course :class in this slot.', self::describe($clash))];
        }
        if (in_array($slotId, $this->blockedRooms[$room->id] ?? [], true)) {
            return [__('The room is used by a TPB class in this slot.')];
        }

        return [];
    }

    /**
     * slotErrors and roomErrors together, for one target place.
     *
     * @return list<string>
     */
    public function placeErrors(int $slotId, Room $room): array
    {
        return [...$this->slotErrors($slotId), ...$this->roomErrors($slotId, $room)];
    }

    /**
     * The program's rooms the gene may use in the slot, smallest fitting room first.
     *
     * @return Collection<int, Room>
     */
    public function freeRooms(int $slotId): Collection
    {
        return ($this->cellRooms[$slotId] ?? collect())->filter(fn (Room $room) => $this->roomErrors($slotId, $room) === [])->values();
    }

    /**
     * @return array{course: string, class: string}
     */
    public static function describe(ScheduleDetail $gene): array
    {
        return ['course' => $gene->course->name, 'class' => CourseLecturer::labelFor($gene->class_number)];
    }
}
