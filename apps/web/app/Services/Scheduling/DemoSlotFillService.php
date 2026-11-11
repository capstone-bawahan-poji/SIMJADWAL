<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\User;

/**
 * Demo helper, never on in production: hands every program of the faculty the cells it is short of,
 * so a demo does not start with a hundred clicks on the grid. It only adds cells (what a program
 * already holds stays) and goes through RoomSlotAllocationService::sync, so the usual rules hold:
 * TPB and other faculties keep their rooms, and a published faculty is refused.
 *
 * Per program it asks SlotSufficiencyService what is missing, gives one cell per missing meeting
 * (a room that seats the class, a slot of the course's SKS type, a slot where the course's
 * lecturers are not teaching TPB), and asks again, a few rounds at most.
 */
readonly class DemoSlotFillService
{
    private const ROUNDS = 4;

    public function __construct(
        private RoomSlotAllocationService $allocations,
        private SlotSufficiencyService $sufficiency,
        private TpbOccupancyResolver $tpbOccupancy,
    ) {}

    /**
     * @return list<array{code: string, added: int, sufficient: bool}>
     */
    public function fill(User $actor, Faculty $faculty, string $period): array
    {
        $grid = $this->allocations->grid($faculty, $period);
        $taken = array_flip(array_map(fn (array $cell) => "{$cell['time_slot_id']}:{$cell['room_id']}", $grid['cells']));
        $typeOf = array_column($grid['time_slots'], 'type', 'id');

        $free = [];
        foreach ($grid['rooms'] as $room) {
            foreach ($grid['time_slots'] as $slot) {
                if (! isset($taken["{$slot['id']}:{$room['id']}"])) {
                    $free[] = ['time_slot_id' => $slot['id'], 'room_id' => $room['id'], 'capacity' => $room['capacity'], 'type' => $slot['type']];
                }
            }
        }
        // Seeded, so a demo can be repeated, and spread over the week instead of piled on Monday.
        mt_srand(2026);
        shuffle($free);

        $tpb = $this->tpbOccupancy->forFaculty($faculty->id, $period);
        $report = [];
        foreach (StudyProgram::query()->where('faculty_id', $faculty->id)->orderBy('code')->get() as $program) {
            $added = 0;
            $check = $this->sufficiency->check($program, $period);
            for ($round = 0; $round < self::ROUNDS && ! $check['sufficient']; $round++) {
                $batch = [];
                foreach ($check['shortages'] as $short) {
                    foreach (range(1, $short['meetings']) as $ignored) {
                        $index = $this->pick($free, $short, $program, $tpb);
                        if ($index === null) {
                            continue;
                        }
                        $batch[] = ['time_slot_id' => $free[$index]['time_slot_id'], 'room_id' => $free[$index]['room_id'], 'study_program_id' => $program->id];
                        unset($free[$index]);
                    }
                }
                if ($batch === []) {
                    break;
                }
                $this->allocations->sync($actor, $faculty, $period, $batch);
                $free = array_values($free);
                $added += count($batch);
                $check = $this->sufficiency->check($program, $period);
            }
            $report[] = ['code' => $program->code, 'added' => $added, 'sufficient' => $check['sufficient']];
        }

        return $report;
    }

    /**
     * The free cell for one missing meeting: seats the class, avoids slots the course cannot use,
     * prefers the SKS type, then the smallest room.
     *
     * @param  list<array{time_slot_id: int, room_id: int, capacity: int, type: int}>  $free
     * @param  array{course_id: int, min_capacity: int, slot_type: int}  $short
     * @param  array<string, mixed>  $tpb
     */
    private function pick(array $free, array $short, StudyProgram $program, array $tpb): ?int
    {
        $course = Course::query()->find($short['course_id'], ['id', 'semester']);
        $lecturers = CourseLecturer::query()->where('course_id', $short['course_id'])->pluck('lecturer_id')->filter()->all();
        $semesterBlocked = array_flip($tpb['programs'][$program->id][$course?->semester] ?? []);

        $best = null;
        $bestKey = null;
        foreach ($free as $i => $cell) {
            if ($cell['capacity'] < $short['min_capacity'] || isset($semesterBlocked[$cell['time_slot_id']])) {
                continue;
            }
            $everyoneBusy = $lecturers !== [] && collect($lecturers)->every(fn (int $id) => in_array($cell['time_slot_id'], $tpb['lecturers'][$id] ?? [], true));
            if ($everyoneBusy) {
                continue;
            }
            $key = [$cell['type'] === $short['slot_type'] ? 0 : 1, $cell['capacity']];
            if ($bestKey === null || $key < $bestKey) {
                $best = $i;
                $bestKey = $key;
            }
        }

        return $best;
    }
}
