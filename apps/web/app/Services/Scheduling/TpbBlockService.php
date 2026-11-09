<?php

namespace App\Services\Scheduling;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ScheduleDetail;

/**
 * Slots taken by the active TPB run of a period. TPB is scheduled before any faculty, so the
 * faculty GA must keep away from them:
 *
 *   - programs: a program's courses of semester s skip the slots of the regular TPB groups
 *     (maba, non_maba, campuran) it attends with target semester s (hard),
 *   - retake: every course of a program keeps one parallel class out of the slots of the
 *     mengulang groups it attends (soft, SC_ULANG),
 *   - lecturers: a lecturer skips the slots of its TPB classes (hard),
 *   - rooms: a room is taken in the slots of the TPB classes placed in it (hard).
 */
class TpbBlockService
{
    /** @var array<string, array{run_id: int|null, programs: array<int, array<int, list<int>>>, retake: array<int, list<int>>, lecturers: array<int, list<int>>, rooms: array<int, list<int>>}> */
    private array $cache = [];

    public function __construct(private readonly TpbRunResolver $tpbRuns) {}

    /**
     * @return array{run_id: int|null, programs: array<int, array<int, list<int>>>, retake: array<int, list<int>>, lecturers: array<int, list<int>>, rooms: array<int, list<int>>}
     */
    public function blocks(string $period): array
    {
        return $this->cache[$period] ??= $this->build($period);
    }

    /**
     * Shape sent to the desktop GA inside the faculty dataset. Lists are sorted, so the
     * dataset fingerprint only changes when the blocks do.
     *
     * @param  list<int>  $studyProgramIds
     * @param  list<int>  $lecturerIds
     * @return array<string, list<array<string, mixed>>>
     */
    public function forDataset(string $period, array $studyProgramIds, array $lecturerIds): array
    {
        $blocks = $this->blocks($period);
        $programs = array_flip($studyProgramIds);
        $lecturers = array_flip($lecturerIds);

        $rows = [];
        foreach ($blocks['programs'] as $programId => $bySemester) {
            if (isset($programs[$programId])) {
                foreach ($bySemester as $semester => $slots) {
                    $rows[] = ['study_program_id' => $programId, 'semester' => $semester, 'time_slot_ids' => $slots];
                }
            }
        }

        return [
            'study_programs' => $this->sorted($rows, ['study_program_id', 'semester']),
            'retake' => $this->sorted(array_values(array_map(
                fn (int $programId) => ['study_program_id' => $programId, 'time_slot_ids' => $blocks['retake'][$programId]],
                array_keys(array_intersect_key($blocks['retake'], $programs)),
            )), ['study_program_id']),
            'lecturers' => $this->sorted(array_values(array_map(
                fn (int $lecturerId) => ['lecturer_id' => $lecturerId, 'time_slot_ids' => $blocks['lecturers'][$lecturerId]],
                array_keys(array_intersect_key($blocks['lecturers'], $lecturers)),
            )), ['lecturer_id']),
        ];
    }

    /**
     * The groups a program attends in the active TPB run of a period, with their slots.
     *
     * @return list<array{course_code: string, course_name: string, group_code: string, cohort: string, target_semester: int|null, time_slot_ids: list<int>}>
     */
    public function placedGroupsFor(StudyProgram $studyProgram, string $period): array
    {
        $run = $this->tpbRuns->active($period);
        if ($run === null) {
            return [];
        }

        $slotsByClass = $this->slotsByClass($run->id);

        return CourseLecturer::query()
            ->with(['course:id,code,name', 'tpbGroup.studyPrograms' => fn ($q) => $q->whereKey($studyProgram->id)])
            ->whereRelation('tpbOffering', 'period', $period)
            ->whereHas('tpbGroup.studyPrograms', fn ($q) => $q->whereKey($studyProgram->id))
            ->get()
            ->groupBy('tpb_group_id')
            ->map(function ($classes) use ($slotsByClass) {
                $class = $classes->first();
                $group = $class->tpbGroup;
                $slots = $classes->flatMap(fn (CourseLecturer $c) => $slotsByClass["{$c->course_id}:{$c->class_number}"] ?? [])->unique()->sort()->values()->all();

                return [
                    'course_code' => $class->course->code,
                    'course_name' => $class->course->name,
                    'group_code' => $group->code,
                    'cohort' => $group->cohort->value,
                    'target_semester' => $group->studyPrograms->first()?->pivot->target_semester,
                    'time_slot_ids' => $slots,
                ];
            })
            ->sortBy([['course_code', 'asc'], ['group_code', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return array{run_id: int|null, programs: array<int, array<int, list<int>>>, retake: array<int, list<int>>, lecturers: array<int, list<int>>, rooms: array<int, list<int>>}
     */
    private function build(string $period): array
    {
        $empty = ['run_id' => null, 'programs' => [], 'retake' => [], 'lecturers' => [], 'rooms' => []];
        $run = $this->tpbRuns->active($period);
        if ($run === null) {
            return $empty;
        }

        $blocks = ['run_id' => $run->id] + $empty;
        $details = ScheduleDetail::query()->where('scheduling_run_id', $run->id)
            ->get(['course_id', 'class_number', 'lecturer_id', 'time_slot_id', 'room_id']);
        foreach ($details as $detail) {
            $blocks['lecturers'][$detail->lecturer_id][] = $detail->time_slot_id;
            if ($detail->room_id !== null) {
                $blocks['rooms'][$detail->room_id][] = $detail->time_slot_id;
            }
        }

        $slotsByClass = $this->slotsByClass($run->id);
        CourseLecturer::query()
            ->with('tpbGroup.studyPrograms:id')
            ->whereRelation('tpbOffering', 'period', $period)
            ->get(['id', 'course_id', 'class_number', 'tpb_group_id'])
            ->each(function (CourseLecturer $class) use (&$blocks, $slotsByClass) {
                $slots = $slotsByClass["{$class->course_id}:{$class->class_number}"] ?? [];
                $group = $class->tpbGroup;
                foreach ($group->studyPrograms as $program) {
                    foreach ($slots as $slot) {
                        if ($group->cohort->isRegular()) {
                            $blocks['programs'][$program->id][(int) $program->pivot->target_semester][] = $slot;
                        } elseif ($group->cohort === TpbCohort::MENGULANG) {
                            $blocks['retake'][$program->id][] = $slot;
                        }
                    }
                }
            });

        foreach (['retake', 'lecturers', 'rooms'] as $key) {
            $blocks[$key] = array_map($this->uniqueSorted(...), $blocks[$key]);
            ksort($blocks[$key]);
        }
        $blocks['programs'] = array_map(fn (array $bySemester) => array_map($this->uniqueSorted(...), $bySemester), $blocks['programs']);
        ksort($blocks['programs']);

        return $blocks;
    }

    /**
     * @return array<string, list<int>> "course_id:class_number" => time_slot_ids
     */
    private function slotsByClass(int $runId): array
    {
        $slots = [];
        ScheduleDetail::query()->where('scheduling_run_id', $runId)
            ->get(['course_id', 'class_number', 'time_slot_id'])
            ->each(function (ScheduleDetail $detail) use (&$slots) {
                $slots["{$detail->course_id}:{$detail->class_number}"][] = $detail->time_slot_id;
            });

        return $slots;
    }

    /**
     * @param  list<int>  $slots
     * @return list<int>
     */
    private function uniqueSorted(array $slots): array
    {
        $slots = array_values(array_unique($slots));
        sort($slots);

        return $slots;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function sorted(array $rows, array $keys): array
    {
        usort($rows, fn (array $a, array $b) => array_map(fn ($k) => $a[$k], $keys) <=> array_map(fn ($k) => $b[$k], $keys));

        return $rows;
    }
}
