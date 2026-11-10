<?php

namespace App\Services\Scheduling\Export;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;

/**
 * JSON of a room grid for the schedule view on the room allocation page. Same grid, same colours
 * and same clash rules as the PDF and Excel export.
 */
final class RoomGridTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(RoomGridExport $export): array
    {
        $grid = $export->grid;
        $cells = [];
        foreach ($grid->buildings as $rooms) {
            foreach ($rooms as $room) {
                foreach ($grid->timeSlots as $slot) {
                    $genes = $grid->cell($slot, $room);
                    if ($genes === []) {
                        continue;
                    }
                    $color = $grid->color($slot, $room);
                    $cells[] = [
                        'time_slot_id' => $slot->id,
                        'room_id' => $room->id,
                        'color' => $color['hex'],
                        'left_border' => $color['leftBorder'],
                        'clash' => $grid->isClash($slot, $room),
                        'classes' => array_map(fn ($d) => [
                            'course' => $d->course->name,
                            'course_code' => $d->course->code,
                            'class_label' => CourseLecturer::labelFor($d->class_number),
                            'lecturer' => $d->lecturer->name,
                            'group' => $grid->mode === ExportColors::MODE_SUPERADMIN ? $grid->groupOf($d) : null,
                        ], $genes),
                    ];
                }
            }
        }

        return [
            'title' => $export->title,
            'subtitle' => $export->subtitle,
            'mode' => $grid->mode,
            'clash_count' => $grid->clashCount(),
            'without_room' => count($grid->withoutRoom),
            'legend' => array_map(fn (array $e) => ['label' => $e['label'], 'color' => $e['hex'], 'left_border' => $e['leftBorder']], $grid->legend),
            'buildings' => array_map(
                fn (string $name, array $rooms) => [
                    'name' => $name,
                    'label' => RoomGrid::buildingLabel($name),
                    'rooms' => array_map(fn (Room $r) => ['id' => $r->id, 'code' => $r->code], $rooms),
                ],
                array_keys($grid->buildings),
                array_values($grid->buildings),
            ),
            'time_slots' => $grid->timeSlots->map(fn (TimeSlot $s) => [
                'id' => $s->id,
                'day' => $s->day->value,
                'day_label' => $s->day->label(),
                'session' => $s->session,
                'time' => ScheduleMatrix::range($s),
            ])->values()->all(),
            'cells' => $cells,
        ];
    }
}
