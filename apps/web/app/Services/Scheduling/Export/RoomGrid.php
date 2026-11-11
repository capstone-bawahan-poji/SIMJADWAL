<?php

namespace App\Services\Scheduling\Export;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Support\Collection;

readonly class RoomGrid
{
    /** @var array<string, list<Room>> building => rooms, ordered */
    public array $buildings;

    /** @var array<int, array<int, list<ScheduleDetail>>> time_slot_id => room_id => genes */
    private array $cells;

    /** @var list<array{slot: TimeSlot, room: Room, details: list<ScheduleDetail>}> */
    public array $roomClashes;

    /** @var list<array{slot: TimeSlot, lecturer: string, details: list<ScheduleDetail>}> */
    public array $lecturerClashes;

    /** @var list<ScheduleDetail> */
    public array $withoutRoom;

    /** @var list<array{label: string, hex: string, leftBorder: ?string}> */
    public array $legend;

    /** @var array<string, true> "time_slot_id|lecturer_id" of a lecturer booked twice in one slot */
    private array $lecturerClashKeys;

    /**
     * @param  Collection<int, ScheduleDetail>  $details  with ScheduleDetailTransformer::relations()
     * @param  Collection<int, TimeSlot>  $timeSlots  ordered by day and session
     * @param  Collection<int, Room>  $rooms
     * @param  array<int, string>  $facultyCodes  study program id => faculty code (FSTI, FPB, FRTI)
     */
    public function __construct(
        Collection $details,
        public Collection $timeSlots,
        Collection $rooms,
        public string $mode = ExportColors::MODE_SUPERADMIN,
        private array $facultyCodes = [],
    ) {
        $buildings = [];
        foreach ($rooms->sortBy([['building', 'asc'], ['code', 'asc']]) as $room) {
            $buildings[$room->building ?: __('No building')][] = $room;
        }

        $cells = [];
        $withoutRoom = [];
        foreach ($details as $detail) {
            if ($detail->room_id === null) {
                $withoutRoom[] = $detail;

                continue;
            }
            $cells[$detail->time_slot_id][$detail->room_id][] = $detail;
        }

        $slots = $timeSlots->keyBy('id');
        $roomsById = $rooms->keyBy('id');
        $roomClashes = [];
        foreach ($cells as $slotId => $byRoom) {
            foreach ($byRoom as $roomId => $genes) {
                if (count($genes) > 1 && isset($roomsById[$roomId], $slots[$slotId])) {
                    $roomClashes[] = ['slot' => $slots[$slotId], 'room' => $roomsById[$roomId], 'details' => $genes];
                }
            }
        }

        $lecturerClashes = [];
        foreach ($details->groupBy(fn (ScheduleDetail $d) => $d->time_slot_id.'|'.$d->lecturer_id) as $genes) {
            if ($genes->count() > 1 && isset($slots[$genes[0]->time_slot_id])) {
                $lecturerClashes[] = ['slot' => $slots[$genes[0]->time_slot_id], 'lecturer' => $genes[0]->lecturer->name, 'details' => $genes->all()];
            }
        }

        $this->buildings = $buildings;
        $this->cells = $cells;
        $this->roomClashes = $roomClashes;
        $this->lecturerClashes = $lecturerClashes;
        $this->withoutRoom = $withoutRoom;
        $this->lecturerClashKeys = array_fill_keys(array_map(fn (array $c) => $c['slot']->id.'|'.$c['details'][0]->lecturer_id, $lecturerClashes), true);
        $this->legend = $this->buildLegend($details);
    }

    /**
     * @return list<ScheduleDetail>
     */
    public function cell(TimeSlot $slot, Room $room): array
    {
        return $this->cells[$slot->id][$room->id] ?? [];
    }

    /**
     * A room used twice in a slot, or a lecturer teaching two classes at once.
     */
    public function isClash(TimeSlot $slot, Room $room): bool
    {
        $genes = $this->cell($slot, $room);

        return count($genes) > 1 || ($genes !== [] && isset($this->lecturerClashKeys[$slot->id.'|'.$genes[0]->lecturer_id]));
    }

    public function clashCount(): int
    {
        return count($this->roomClashes) + count($this->lecturerClashes);
    }

    /**
     * @return array{hex: string, leftBorder: ?string}|null null for an empty cell
     */
    public function color(TimeSlot $slot, Room $room): ?array
    {
        $genes = $this->cell($slot, $room);

        return $genes === [] ? null : ExportColors::getColor($this->mode, $this->unit($genes[0]));
    }

    /**
     * Text of one cell: course and class, then the lecturer, one block per gene. Superadmin cells end
     * with the group code, so the colour is never the only hint.
     *
     * @param  list<ScheduleDetail>  $genes
     */
    public function text(array $genes): string
    {
        return implode("\n---\n", array_map(
            fn (ScheduleDetail $d) => $d->course->name.' · '.CourseLecturer::labelFor($d->class_number)."\n".$d->lecturer->name
                .($this->mode === ExportColors::MODE_SUPERADMIN ? "\n".$this->groupOf($d) : ''),
            $genes,
        ));
    }

    public function groupOf(ScheduleDetail $d): string
    {
        return $d->course->is_tpb ? 'TPB' : ($this->facultyCodes[$d->course->study_program_id] ?? '');
    }

    /**
     * The key of the colour map for this gene in this mode.
     */
    private function unit(ScheduleDetail $d): string
    {
        return match ($this->mode) {
            ExportColors::MODE_TPB => $d->course->name,
            ExportColors::MODE_FACULTY => $d->course->studyProgram?->code ?? '',
            default => $this->groupOf($d),
        };
    }

    /**
     * @param  Collection<int, ScheduleDetail>  $details
     * @return list<array{label: string, hex: string, leftBorder: ?string}>
     */
    private function buildLegend(Collection $details): array
    {
        if ($this->mode === ExportColors::MODE_SUPERADMIN) {
            return array_map(
                fn (string $group) => ['label' => $group, ...ExportColors::getColor($this->mode, $group)],
                array_keys(ExportColors::GROUP_HUES),
            );
        }

        $units = [];
        foreach ($details as $d) {
            $units[$this->unit($d)] ??= $this->mode === ExportColors::MODE_TPB ? $d->course->name : ($d->course->studyProgram?->name ?? $d->course->name);
        }
        asort($units);

        return array_map(
            fn (string $key, string $label) => ['label' => $label, ...ExportColors::getColor($this->mode, $key)],
            array_keys($units),
            $units,
        );
    }

    /**
     * "Gedung E" is stored with its prefix; a bare "E" gets it.
     */
    public static function buildingLabel(string $building): string
    {
        return preg_match('/^gedung\b/i', $building) ? $building : __('Building').' '.$building;
    }
}
