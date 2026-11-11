<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;

/**
 * POS step 3: do the program's cells hold all its class meetings? One meeting needs one cell whose
 * room fits class_capacity (the system has no count of active students per course). A 4 SKS course
 * meets twice. Greedy: biggest classes first, each takes the smallest room that fits, preferring a
 * slot of the course's SKS type (SC_SKS: 3 SKS in type 3, 2 and 4 SKS in type 2).
 *
 * Only rooms and counts are checked here. Lecturer and class clashes are the GA's job, so a program
 * may be "sufficient" and still get a run with violations.
 */
readonly class SlotSufficiencyService
{
    public function __construct(private RoomSlotAllocationService $allocations) {}

    /**
     * @return array{allocated_cells: int, required_meetings: int, sufficient: bool, type_mismatches: int, shortages: list<array{course_id: int, code: string, name: string, meetings: int, min_capacity: int, slot_type: int}>}
     */
    public function check(StudyProgram $studyProgram, string $period): array
    {
        $cells = collect($this->allocations->cellsOf($studyProgram, $period))
            ->sortBy([['capacity', 'asc'], ['time_slot_id', 'asc'], ['room_id', 'asc']])
            ->values()
            ->all();

        $needs = [];
        Course::query()
            ->where('study_program_id', $studyProgram->id)
            ->where('is_tpb', false)
            ->where('is_active', true)
            ->whereIn('semester', Period::semesters($period))
            ->orderBy('id')
            ->get()
            ->each(function (Course $course) use (&$needs) {
                foreach (range(1, ($course->parallel_class_count ?? 1) * ($course->sks === 4 ? 2 : 1)) as $ignored) {
                    $needs[] = $course;
                }
            });
        usort($needs, fn (Course $a, Course $b) => [$b->class_capacity, $a->id] <=> [$a->class_capacity, $b->id]);

        $free = array_fill(0, count($cells), true);
        $short = [];
        $mismatches = 0;
        foreach ($needs as $course) {
            $type = self::slotType($course->sks);
            $fallback = null;
            $taken = null;
            foreach ($cells as $i => $cell) {
                if (! $free[$i] || $cell['capacity'] < $course->class_capacity) {
                    continue;
                }
                if ($cell['slot_type'] === $type) {
                    $taken = $i;
                    break;
                }
                $fallback ??= $i;
            }
            if ($taken === null && $fallback !== null) {
                $taken = $fallback;
                $mismatches++;
            }
            if ($taken === null) {
                $short[$course->id] ??= [
                    'course_id' => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                    'meetings' => 0,
                    'min_capacity' => $course->class_capacity,
                    'slot_type' => $type,
                ];
                $short[$course->id]['meetings']++;

                continue;
            }
            $free[$taken] = false;
        }

        return [
            'allocated_cells' => count($cells),
            'required_meetings' => count($needs),
            'sufficient' => $short === [],
            'type_mismatches' => $mismatches,
            'shortages' => array_values($short),
        ];
    }

    public static function slotType(int $sks): int
    {
        return $sks === 3 ? 3 : 2;
    }
}
