<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Support\Collection;

/**
 * Hard constraints for moving one gene of an active run, checked in memory. Built by
 * ScheduleSlotCheckerFactory; PATCH and the options endpoint share it.
 *
 *   - slot: HC2 lecturer free (all faculties + TPB), HC3 class free, HC4 parallel limit, TPB program block,
 *   - room: usable by the faculty, big enough, free in every active schedule, not used by TPB.
 */
readonly class ScheduleSlotChecker
{
    /**
     * @param  array<int, int>  $programCount  slot => genes of the same program in the run
     * @param  array<int, ScheduleDetail>  $cohortAt  slot => gene of the same program, semester and class
     * @param  list<int>  $blockedForProgram
     * @param  list<int>  $blockedForLecturer
     * @param  array<int, list<int>>  $blockedRooms  room_id => slots
     * @param  Collection<int, Room>  $candidateRooms  usable and big enough, smallest first
     */
    public function __construct(
        private ScheduleDetail $detail,
        private ActiveScheduleOccupancy $occupancy,
        private array $programCount,
        private array $cohortAt,
        private array $blockedForProgram,
        private array $blockedForLecturer,
        private array $blockedRooms,
        private Collection $candidateRooms,
        private int $parallelLimit,
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

        if (($this->programCount[$slotId] ?? 0) + 1 > $this->parallelLimit) {
            $errors[] = __('The study program already has :limit classes in this slot.', ['limit' => $this->parallelLimit]);
        }

        if (in_array($slotId, $this->blockedForProgram, true)) {
            $errors[] = __('The slot is reserved for a TPB class of this semester.');
        }

        return $errors;
    }

    /**
     * Why the room cannot be used in the slot. Empty = the room fits and is free.
     *
     * @return list<string>
     */
    public function roomErrors(int $slotId, Room $room): array
    {
        if ($room->faculty_id !== null && $room->faculty_id !== $this->detail->schedulingRun->faculty_id) {
            return [__('The room cannot be used by this faculty.')];
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
     * Rooms the gene may use in the slot, smallest fitting room first.
     *
     * @return Collection<int, Room>
     */
    public function freeRooms(int $slotId): Collection
    {
        return $this->candidateRooms->filter(fn (Room $room) => $this->roomErrors($slotId, $room) === [])->values();
    }

    /**
     * @return array{course: string, class: string}
     */
    public static function describe(ScheduleDetail $gene): array
    {
        return ['course' => $gene->course->name, 'class' => CourseLecturer::labelFor($gene->class_number)];
    }
}