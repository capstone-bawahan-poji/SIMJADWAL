<?php

namespace App\Services\Scheduling;

use App\Enums\MasterData\TpbCohort;

/**
 * Re-checks an uploaded GA result against the dataset it was computed from. Structural problems
 * are errors; hard constraint violations are counted so they can be compared with what the
 * desktop reported.
 *
 *   - study_program: every meeting sits in one of the program's cells (slot, room), one meeting per
 *     cell, in a room that fits. HC2 lecturer, HC3 class (program, semester, class). HC4 does not
 *     apply: the cells already cap the classes per slot. A gene in a slot blocked by TPB (program
 *     semester or lecturer) or held by the lecturer in another program's active run is an error,
 *     as the GA never places one there.
 *   - tpb: classes of one group share the slot of every meeting. HC2 lecturer, HC3 = a program
 *     attending two regular groups of the same target semester in one slot (counted per group
 *     meeting, not per class). HC4 does not apply.
 */
class ScheduleIntegrityChecker
{
    /**
     * @param  array<string, mixed>  $dataset
     * @param  list<array<string, int|null>>  $details
     * @return array{errors: array<string, list<string>>, hard: array{HC1: int, HC2: int, HC3: int, HC4: int}}
     */
    public function check(array $dataset, array $details): array
    {
        $tpb = ($dataset['scope'] ?? 'study_program') === 'tpb';
        $errors = [];
        $courses = collect($dataset['courses'])->keyBy('id');
        $slotIds = collect($dataset['time_slots'])->pluck('id')->flip();
        $rooms = collect($dataset['rooms'])->keyBy('id');
        $occupied = collect($dataset['occupied_rooms'])->map(fn (array $o) => "{$o['time_slot_id']}:{$o['room_id']}")->flip();
        $cells = collect($dataset['cells'] ?? [])->map(fn (array $c) => "{$c['time_slot_id']}:{$c['room_id']}")->flip();
        $busy = collect($dataset['lecturer_busy'] ?? [])->mapWithKeys(fn (array $row) => [$row['lecturer_id'] => $row['time_slot_ids']]);

        $expected = [];
        $lecturerOf = [];
        $groupOf = [];
        $fixedSlots = [];
        $groupById = collect($dataset['tpb_groups'] ?? [])->keyBy('id');
        foreach ($dataset['classes'] as $class) {
            $course = $courses[$class['course_id']] ?? null;
            if ($course === null) {
                continue;
            }
            $lecturerOf["{$class['course_id']}:{$class['class_number']}"] = $class['lecturer_id'];
            $groupOf["{$class['course_id']}:{$class['class_number']}"] = $class['tpb_group_id'] ?? null;
            $fixedSlots["{$class['course_id']}:{$class['class_number']}"] = $class['fixed_time_slot_ids'] ?? [];
            foreach (range(1, $course['sks'] === 4 ? 2 : 1) as $meeting) {
                $expected["{$class['course_id']}:{$class['class_number']}:{$meeting}"] = true;
            }
        }

        [$blockedProgram, $blockedLecturer] = $this->facultyBlocks($dataset['tpb_blocks'] ?? null);

        $seen = [];
        $roomUse = [];
        $hard = ['HC1' => 0, 'HC2' => 0, 'HC3' => 0, 'HC4' => 0];
        $lecturerSlot = $cohortSlot = [];
        $unitSlot = [];

        foreach ($details as $i => $d) {
            $key = "{$d['course_id']}:{$d['class_number']}:{$d['meeting_number']}";
            if (! isset($expected[$key]) || isset($seen[$key])) {
                $errors['schedule_details'] = [__('The schedule does not cover exactly the classes of the dataset.')];

                continue;
            }
            $seen[$key] = true;
            $course = $courses[$d['course_id']];
            $slot = $d['time_slot_id'];

            if (($lecturerOf["{$d['course_id']}:{$d['class_number']}"] ?? null) !== $d['lecturer_id']) {
                $errors["schedule_details.{$i}.lecturer_id"] = [__('The lecturer does not match the teaching assignment.')];
                $hard['HC1']++;
            }
            if (! isset($slotIds[$slot])) {
                $errors["schedule_details.{$i}.time_slot_id"] = [__('Unknown time slot.')];
            }
            if (! $tpb && $d['room_id'] === null) {
                $errors["schedule_details.{$i}.room_id"] = [__('Every meeting needs a room from the program\'s room slots.')];
            }
            if ($d['room_id'] !== null) {
                $room = $rooms[$d['room_id']] ?? null;
                $use = "{$slot}:{$d['room_id']}";
                if ($room === null || (! $tpb && ! isset($cells[$use]))) {
                    $errors["schedule_details.{$i}.room_id"] = [__('The room is not one of the program\'s room slots at this time.')];
                } elseif ($room['capacity'] < $course['class_capacity']) {
                    $errors["schedule_details.{$i}.room_id"] = [__('The room is too small for the class.')];
                } elseif (isset($roomUse[$use]) || isset($occupied[$use])) {
                    $errors["schedule_details.{$i}.room_id"] = [__('The room is already used at this time.')];
                }
                $roomUse[$use] = true;
            }

            $lecturerSlot["{$d['lecturer_id']}:{$slot}"] = ($lecturerSlot["{$d['lecturer_id']}:{$slot}"] ?? 0) + 1;

            if ($tpb) {
                $unit = ($groupOf["{$d['course_id']}:{$d['class_number']}"] ?? 'none').":{$d['meeting_number']}";
                if (isset($unitSlot[$unit]) && $unitSlot[$unit] !== $slot) {
                    $errors["schedule_details.{$i}.time_slot_id"] = [__('Classes of one TPB group must share the slot of each meeting.')];
                }
                $unitSlot[$unit] ??= $slot;

                // Revision: unchanged groups keep their published slot, revised ones avoid slots faculties hold.
                $fixed = $fixedSlots["{$d['course_id']}:{$d['class_number']}"][$d['meeting_number'] - 1] ?? null;
                if ($fixed !== null && $fixed !== $slot) {
                    $errors["schedule_details.{$i}.time_slot_id"] = [__('This group is not revised: it keeps its published slot.')];
                } elseif ($fixed === null) {
                    $group = $groupById[$groupOf["{$d['course_id']}:{$d['class_number']}"] ?? 0] ?? null;
                    $takenByFaculty = in_array($slot, $blockedLecturer[$d['lecturer_id']] ?? [], true);
                    foreach ($group['study_programs'] ?? [] as $participant) {
                        if ($group !== null && TpbCohort::from($group['cohort'])->isRegular()
                            && in_array($slot, $blockedProgram["{$participant['id']}:{$participant['target_semester']}"] ?? [], true)) {
                            $takenByFaculty = true;
                        }
                    }
                    if ($takenByFaculty) {
                        $errors["schedule_details.{$i}.time_slot_id"] = [__('The slot is already held by an active faculty schedule.')];
                    }
                }

                continue;
            }

            if (in_array($slot, $blockedProgram["{$course['study_program_id']}:{$course['semester']}"] ?? [], true)) {
                $errors["schedule_details.{$i}.time_slot_id"] = [__('The slot is reserved for a TPB class of this semester.')];
            } elseif (in_array($slot, $blockedLecturer[$d['lecturer_id']] ?? [], true)) {
                $errors["schedule_details.{$i}.time_slot_id"] = [__('The lecturer teaches a TPB class in this slot.')];
            } elseif (in_array($slot, $busy[$d['lecturer_id']] ?? [], true)) {
                $errors["schedule_details.{$i}.time_slot_id"] = [__('The lecturer already teaches another program\'s class in this slot.')];
            }

            $cohort = "{$course['study_program_id']}:{$course['semester']}:{$d['class_number']}:{$slot}";
            $cohortSlot[$cohort] = ($cohortSlot[$cohort] ?? 0) + 1;
        }

        if (count($seen) !== count($expected)) {
            $errors['schedule_details'] = [__('The schedule does not cover exactly the classes of the dataset.')];
        }

        if ($tpb) {
            $cohortSlot = $this->tpbCohortCounts($dataset['tpb_groups'] ?? [], $unitSlot);
        }

        $excess = fn (array $counts) => array_sum(array_map(fn (int $c) => max(0, $c - 1), $counts));
        $hard['HC2'] = $excess($lecturerSlot);
        $hard['HC3'] = $excess($cohortSlot);

        return ['errors' => $errors, 'hard' => $hard];
    }

    /**
     * @param  array<string, mixed>|null  $blocks
     * @return array{0: array<string, list<int>>, 1: array<int, list<int>>}
     */
    private function facultyBlocks(?array $blocks): array
    {
        $program = [];
        foreach ($blocks['study_programs'] ?? [] as $row) {
            $program["{$row['study_program_id']}:{$row['semester']}"] = $row['time_slot_ids'];
        }
        $lecturer = [];
        foreach ($blocks['lecturers'] ?? [] as $row) {
            $lecturer[$row['lecturer_id']] = $row['time_slot_ids'];
        }

        return [$program, $lecturer];
    }

    /**
     * Group meetings per (program, target semester, slot), regular groups only.
     *
     * @param  list<array<string, mixed>>  $groups
     * @param  array<string, int>  $unitSlot  "group_id:meeting" => slot
     * @return array<string, int>
     */
    private function tpbCohortCounts(array $groups, array $unitSlot): array
    {
        $regular = collect($groups)->filter(fn (array $g) => TpbCohort::from($g['cohort'])->isRegular())->keyBy('id');
        $counts = [];
        foreach ($unitSlot as $unit => $slot) {
            [$groupId] = explode(':', $unit);
            foreach ($regular[(int) $groupId]['study_programs'] ?? [] as $program) {
                $key = "{$program['id']}:{$program['target_semester']}:{$slot}";
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
