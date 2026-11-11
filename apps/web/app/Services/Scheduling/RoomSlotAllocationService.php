<?php

namespace App\Services\Scheduling;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\SlotRequest;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * POS steps 1, 5 and 6: the faculty hands cells (period, slot, room) to its study programs.
 * Rooms are the faculty's own and the shared ones. A cell cannot go to a program when TPB holds
 * it or another faculty already has it. A cell may move between programs (a swap) while its
 * holder is still drafting; the holder's runs then go stale.
 */
readonly class RoomSlotAllocationService
{
    public function __construct(
        private DatabaseManager $db,
        private TpbOccupancyResolver $tpb,
        private ProgramScheduleState $state,
    ) {}

    /**
     * The faculty's grid. cells lists only the cells that are not free:
     *   - allocated: held by a program of this faculty (locked = its holder handed the schedule in),
     *   - other_faculty: a shared room held by another faculty,
     *   - tpb: held by TPB, conflict: held by TPB and still allocated (TPB published after the allocation).
     *
     * @return array<string, mixed>
     */
    public function grid(Faculty $faculty, string $period): array
    {
        $rooms = $this->rooms($faculty);
        $roomIds = $rooms->pluck('id')->flip();
        $programs = StudyProgram::query()->where('faculty_id', $faculty->id)->orderBy('code')->get(['id', 'code', 'name']);
        $statuses = $this->state->statuses($programs->pluck('id')->all(), $period);
        $tpb = $this->tpb->forFaculty($faculty->id, $period);
        $tpbCells = $this->tpb->cells($faculty->id, $period);

        $allocations = RoomSlotAllocation::query()
            ->with('faculty:id,code')
            ->where('period', $period)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->get();

        $cells = [];
        foreach ($allocations as $allocation) {
            $key = "{$allocation->time_slot_id}:{$allocation->room_id}";
            $own = $allocation->faculty_id === $faculty->id;
            $cells[$key] = [
                'time_slot_id' => $allocation->time_slot_id,
                'room_id' => $allocation->room_id,
                'status' => ! $own ? 'other_faculty' : (isset($tpbCells[$key]) ? 'conflict' : 'allocated'),
                'study_program_id' => $own ? $allocation->study_program_id : null,
                'faculty' => $own ? null : ['id' => $allocation->faculty_id, 'code' => $allocation->faculty->code],
                'slot_request_id' => $own ? $allocation->slot_request_id : null,
                'locked' => $own && ! $statuses[$allocation->study_program_id]->isEditable(),
            ];
        }
        foreach (array_keys($tpbCells) as $key) {
            [$slot, $room] = array_map('intval', explode(':', $key));
            if (isset($roomIds[$room]) && ! isset($cells[$key])) {
                $cells[$key] = ['time_slot_id' => $slot, 'room_id' => $room, 'status' => 'tpb', 'study_program_id' => null, 'faculty' => null, 'slot_request_id' => null, 'locked' => true];
            }
        }
        $counts = $allocations->where('faculty_id', $faculty->id)->countBy('study_program_id');

        return [
            'period' => $period,
            'faculty' => ['id' => $faculty->id, 'code' => $faculty->code, 'name' => $faculty->name],
            'tpb_source' => ['type' => $tpb['source'], 'run_id' => $tpb['run_id'], 'import_id' => $tpb['import_id']],
            'published' => $this->state->isFacultyPublished($faculty->id, $period),
            'study_programs' => $programs->map(fn (StudyProgram $program) => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'cells_count' => $counts[$program->id] ?? 0,
                'submission_status' => $statuses[$program->id]->value,
            ])->values()->all(),
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'building' => $room->building,
                'capacity' => $room->capacity,
                'faculty_id' => $room->faculty_id,
            ])->values()->all(),
            'time_slots' => $this->timeSlots(),
            'cells' => collect($cells)->sortBy([['time_slot_id', 'asc'], ['room_id', 'asc']])->values()->all(),
        ];
    }

    /**
     * Cells of one program, with what the GA and the sufficiency check need.
     *
     * @return list<array{time_slot_id: int, room_id: int, room_code: string, capacity: int, slot_type: int, day: int, session: int}>
     */
    public function cellsOf(StudyProgram $studyProgram, string $period): array
    {
        return RoomSlotAllocation::query()
            ->with(['room:id,code,capacity', 'timeSlot:id,day,session,type'])
            ->where('study_program_id', $studyProgram->id)
            ->where('period', $period)
            ->orderBy('time_slot_id')
            ->orderBy('room_id')
            ->get()
            ->map(fn (RoomSlotAllocation $a) => [
                'time_slot_id' => $a->time_slot_id,
                'room_id' => $a->room_id,
                'room_code' => $a->room->code,
                'capacity' => $a->room->capacity,
                'slot_type' => $a->timeSlot->type,
                'day' => $a->timeSlot->day->value,
                'session' => $a->timeSlot->session,
            ])->values()->all();
    }

    /**
     * Sets the holder of each listed cell (study_program_id null = take the cell back). Cells not
     * listed stay as they are. All or nothing.
     *
     * @param  list<array{time_slot_id: int, room_id: int, study_program_id: int|null}>  $cells
     * @return array{allocated: int, released: int}
     */
    public function sync(User $actor, Faculty $faculty, string $period, array $cells, ?SlotRequest $request = null): array
    {
        $this->state->assertFacultyNotPublished($faculty->id, $period);
        if ($this->tpb->forFaculty($faculty->id, $period)['source'] === null) {
            throw ApiException::invalidState(__('The TPB schedule of :period is not available yet. Import it or wait for TPB to publish it before handing out rooms.', ['period' => Period::label($period)]));
        }

        try {
            return $this->db->transaction(fn () => $this->apply($actor, $faculty, $period, $cells, $request));
        } catch (UniqueConstraintViolationException) {
            throw ApiException::invalidState(__('Another faculty took one of these rooms at the same time. Reload and try again.'));
        }
    }

    /**
     * @param  list<array{time_slot_id: int, room_id: int, study_program_id: int|null}>  $cells
     * @return array{allocated: int, released: int}
     */
    private function apply(User $actor, Faculty $faculty, string $period, array $cells, ?SlotRequest $request): array
    {
        // Serialises allocation changes of one faculty and period.
        $current = RoomSlotAllocation::query()->where('period', $period)
            ->whereIn('room_id', $this->rooms($faculty)->pluck('id'))
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (RoomSlotAllocation $a) => "{$a->time_slot_id}:{$a->room_id}");

        $rooms = $this->rooms($faculty)->keyBy('id');
        $slotIds = TimeSlot::query()->pluck('id')->flip();
        $programIds = StudyProgram::query()->where('faculty_id', $faculty->id)->pluck('id')->flip();
        $statuses = $this->state->statuses($programIds->keys()->all(), $period);
        $tpbCells = $this->tpb->cells($faculty->id, $period);

        $errors = [];
        $seen = [];
        foreach ($cells as $i => $cell) {
            $key = "{$cell['time_slot_id']}:{$cell['room_id']}";
            $holder = $current[$key] ?? null;
            $target = $cell['study_program_id'];

            if (isset($seen[$key])) {
                $errors["cells.{$i}"] = [__('The cell is listed twice.')];
            } elseif (! isset($rooms[$cell['room_id']])) {
                $errors["cells.{$i}.room_id"] = [__('The room cannot be used by this faculty.')];
            } elseif (! isset($slotIds[$cell['time_slot_id']])) {
                $errors["cells.{$i}.time_slot_id"] = [__('Unknown time slot.')];
            } elseif ($target !== null && ! isset($programIds[$target])) {
                $errors["cells.{$i}.study_program_id"] = [__('The study program does not belong to this faculty.')];
            } elseif ($holder !== null && $holder->faculty_id !== $faculty->id) {
                $errors["cells.{$i}"] = [__('Another faculty already holds this room in this slot.')];
            } elseif ($target !== null && isset($tpbCells[$key])) {
                $errors["cells.{$i}"] = [__('TPB uses this room in this slot.')];
            } elseif ($holder !== null && $holder->study_program_id !== $target && ! $statuses[$holder->study_program_id]->isEditable()) {
                $errors["cells.{$i}"] = [__('The cell is used by a schedule already handed to the faculty.')];
            }
            $seen[$key] = true;
        }
        if ($errors !== []) {
            throw ApiException::validation($errors);
        }

        $allocated = 0;
        $released = 0;
        foreach ($cells as $cell) {
            $holder = $current["{$cell['time_slot_id']}:{$cell['room_id']}"] ?? null;
            if ($cell['study_program_id'] === null) {
                if ($holder !== null) {
                    $holder->delete();
                    $released++;
                }

                continue;
            }
            if ($holder?->study_program_id === $cell['study_program_id']) {
                continue;
            }
            $attributes = ['study_program_id' => $cell['study_program_id'], 'slot_request_id' => $request?->id, 'allocated_by' => $actor->id];
            $holder !== null
                ? $holder->update($attributes)
                : RoomSlotAllocation::query()->create([
                    'period' => $period,
                    'faculty_id' => $faculty->id,
                    'time_slot_id' => $cell['time_slot_id'],
                    'room_id' => $cell['room_id'],
                    ...$attributes,
                ]);
            $allocated++;
        }

        activity('scheduling')->causedBy($actor)->event('room_slot_allocation.synced')
            ->withProperties(['faculty_id' => $faculty->id, 'period' => $period, 'allocated' => $allocated, 'released' => $released, 'slot_request_id' => $request?->id])
            ->log(__('Updated the room slots of :faculty for :period', ['faculty' => $faculty->code, 'period' => Period::label($period)]));

        return ['allocated' => $allocated, 'released' => $released];
    }

    /**
     * @return Collection<int, Room>
     */
    private function rooms(Faculty $faculty): Collection
    {
        return Room::query()
            ->where(fn ($q) => $q->where('faculty_id', $faculty->id)->orWhereNull('faculty_id'))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'building', 'capacity', 'faculty_id']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeSlots(): array
    {
        return TimeSlot::query()->orderBy('day')->orderBy('session')->get()
            ->map(fn (TimeSlot $slot) => [
                'id' => $slot->id,
                'day' => $slot->day->value,
                'session' => $slot->session,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'type' => $slot->type,
            ])->values()->all();
    }
}
