<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ScheduleDetail;

/**
 * POS step 3: do the program's cells hold all its class meetings? One meeting needs one cell whose
 * room fits class_capacity, in a slot where the class can run: its lecturer is not teaching TPB or
 * another program then, and the program's semester has no TPB class then. A 4 SKS course meets
 * twice. Meetings are matched to cells (maximum matching, so one meeting never blocks another it
 * could have moved around), preferring the smallest room and a slot of the course's SKS type
 * (SC_SKS: 3 SKS in type 3, 2 and 4 SKS in type 2).
 *
 * Without the slot rule, cells in a slot where every lecturer of the program is busy counted as
 * room for the program although the GA can never use them, and the check said "enough" for a
 * schedule the server would reject.
 *
 * Lecturer clashes inside the program and class clashes are still the GA's job, so a program may
 * be "sufficient" and still get a run with violations.
 */
readonly class SlotSufficiencyService
{
    public function __construct(
        private RoomSlotAllocationService $allocations,
        private TpbOccupancyResolver $tpbOccupancy,
    ) {}

    /**
     * @return array{allocated_cells: int, required_meetings: int, required_by_type: array<int, int>, sufficient: bool, type_mismatches: int, unusable_cells: int, shortages: list<array{course_id: int, code: string, name: string, meetings: int, min_capacity: int, slot_type: int}>}
     */
    public function check(StudyProgram $studyProgram, string $period): array
    {
        $cells = collect($this->allocations->cellsOf($studyProgram, $period))
            ->sortBy([['capacity', 'asc'], ['time_slot_id', 'asc'], ['room_id', 'asc']])
            ->values()
            ->all();

        $courses = Course::query()
            ->where('study_program_id', $studyProgram->id)
            ->where('is_tpb', false)
            ->where('is_active', true)
            ->whereIn('semester', Period::semesters($period))
            ->orderBy('id')
            ->get();
        $lecturers = CourseLecturer::query()->whereIn('course_id', $courses->pluck('id'))->get()
            ->mapWithKeys(fn (CourseLecturer $c) => ["{$c->course_id}:{$c->class_number}" => $c->lecturer_id]);
        $tpb = $this->tpbOccupancy->forFaculty($studyProgram->faculty_id, $period);
        $busy = $this->busyElsewhere($studyProgram, $period, $lecturers->filter()->unique()->values()->all());

        $needs = [];
        foreach ($courses as $course) {
            foreach (range(1, $course->parallel_class_count ?? 1) as $class) {
                $lecturerId = $lecturers["{$course->id}:{$class}"] ?? null;
                $blocked = array_flip([
                    ...($tpb['programs'][$studyProgram->id][$course->semester] ?? []),
                    ...($lecturerId === null ? [] : ($tpb['lecturers'][$lecturerId] ?? [])),
                    ...($lecturerId === null ? [] : ($busy[$lecturerId] ?? [])),
                ]);
                foreach (range(1, $course->sks === 4 ? 2 : 1) as $ignored) {
                    $needs[] = ['course' => $course, 'blocked' => $blocked];
                }
            }
        }
        usort($needs, fn (array $a, array $b) => [$b['course']->class_capacity, $a['course']->id] <=> [$a['course']->class_capacity, $b['course']->id]);

        $requiredByType = [2 => 0, 3 => 0];
        foreach ($needs as $need) {
            $requiredByType[self::slotType($need['course']->sks)]++;
        }

        // Candidate cells of each meeting, the best fit first: right slot type, then the smallest room.
        $candidates = [];
        foreach ($needs as $i => $need) {
            $type = self::slotType($need['course']->sks);
            $fits = array_filter(
                array_keys($cells),
                fn (int $c) => $cells[$c]['capacity'] >= $need['course']->class_capacity && ! isset($need['blocked'][$cells[$c]['time_slot_id']]),
            );
            usort($fits, fn (int $a, int $b) => [$cells[$a]['slot_type'] === $type ? 0 : 1, $a] <=> [$cells[$b]['slot_type'] === $type ? 0 : 1, $b]);
            $candidates[$i] = $fits;
        }

        $owner = [];
        $matched = [];
        foreach (array_keys($needs) as $i) {
            $visited = [];
            if ($this->augment($i, $candidates, $owner, $visited)) {
                $matched[$i] = true;
            }
        }

        $short = [];
        foreach ($needs as $i => $need) {
            if (isset($matched[$i])) {
                continue;
            }
            $course = $need['course'];
            $short[$course->id] ??= [
                'course_id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'meetings' => 0,
                'min_capacity' => $course->class_capacity,
                'slot_type' => self::slotType($course->sks),
            ];
            $short[$course->id]['meetings']++;
        }

        $mismatches = 0;
        foreach ($owner as $cellIndex => $needIndex) {
            if ($cells[$cellIndex]['slot_type'] !== self::slotType($needs[$needIndex]['course']->sks)) {
                $mismatches++;
            }
        }

        $usable = [];
        foreach ($candidates as $fits) {
            foreach ($fits as $c) {
                $usable[$c] = true;
            }
        }

        return [
            'allocated_cells' => count($cells),
            'required_meetings' => count($needs),
            'required_by_type' => $requiredByType,
            'sufficient' => $short === [],
            'type_mismatches' => $mismatches,
            'unusable_cells' => count($cells) - count($usable),
            'shortages' => array_values($short),
        ];
    }

    public static function slotType(int $sks): int
    {
        return $sks === 3 ? 3 : 2;
    }

    /**
     * Kuhn's augmenting path: seats meeting $need, moving a meeting that already holds a cell to
     * another of its candidates when that frees one.
     *
     * @param  array<int, list<int>>  $candidates
     * @param  array<int, int>  $owner  cell index => meeting index
     * @param  array<int, true>  $visited
     */
    private function augment(int $need, array $candidates, array &$owner, array &$visited): bool
    {
        foreach ($candidates[$need] as $cell) {
            if (isset($visited[$cell])) {
                continue;
            }
            $visited[$cell] = true;
            if (! isset($owner[$cell]) || $this->augment($owner[$cell], $candidates, $owner, $visited)) {
                $owner[$cell] = $need;

                return true;
            }
        }

        return false;
    }

    /**
     * Slots the lecturers already teach in the active runs of other programs of the period.
     *
     * @param  list<int>  $lecturerIds
     * @return array<int, list<int>>
     */
    private function busyElsewhere(StudyProgram $studyProgram, string $period, array $lecturerIds): array
    {
        return ScheduleDetail::query()
            ->join('scheduling_runs', 'scheduling_runs.id', '=', 'schedule_details.scheduling_run_id')
            ->where('scheduling_runs.is_active', true)
            ->where('scheduling_runs.period', $period)
            ->whereNotNull('scheduling_runs.faculty_id')
            ->where(fn ($q) => $q->whereNull('scheduling_runs.study_program_id')->orWhere('scheduling_runs.study_program_id', '!=', $studyProgram->id))
            ->whereIn('schedule_details.lecturer_id', $lecturerIds)
            ->distinct()
            ->get(['schedule_details.lecturer_id', 'schedule_details.time_slot_id'])
            ->groupBy('lecturer_id')
            ->map(fn ($rows) => $rows->pluck('time_slot_id')->all())
            ->all();
    }
}
