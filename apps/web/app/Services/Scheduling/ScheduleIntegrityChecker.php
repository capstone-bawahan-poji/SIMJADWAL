<?php

namespace App\Services\Scheduling;

class ScheduleIntegrityChecker
{
    /**
     * @param  array<string, mixed>  $dataset
     * @param  list<array<string, int|null>>  $details
     * @return array{errors: array<string, list<string>>, hard: array{HC1: int, HC2: int, HC3: int, HC4: int}}
     */
    public function check(array $dataset, array $details, int $parallelClassLimit): array
    {
        $errors = [];
        $courses = collect($dataset['courses'])->keyBy('id');
        $slotIds = collect($dataset['time_slots'])->pluck('id')->flip();
        $rooms = collect($dataset['rooms'])->keyBy('id');
        $occupied = collect($dataset['occupied_rooms'])->map(fn (array $o) => "{$o['time_slot_id']}:{$o['room_id']}")->flip();

        $expected = [];
        $lecturerOf = [];
        foreach ($dataset['classes'] as $class) {
            $course = $courses[$class['course_id']] ?? null;
            if ($course === null) {
                continue;
            }
            $lecturerOf["{$class['course_id']}:{$class['class_number']}"] = $class['lecturer_id'];
            foreach (range(1, $course['sks'] === 4 ? 2 : 1) as $meeting) {
                $expected["{$class['course_id']}:{$class['class_number']}:{$meeting}"] = true;
            }
        }

        $seen = [];
        $roomUse = [];
        $hard = ['HC1' => 0, 'HC2' => 0, 'HC3' => 0, 'HC4' => 0];
        $lecturerSlot = $cohortSlot = $programSlot = [];

        foreach ($details as $i => $d) {
            $key = "{$d['course_id']}:{$d['class_number']}:{$d['meeting_number']}";
            if (! isset($expected[$key]) || isset($seen[$key])) {
                $errors['schedule_details'] = [__('The schedule does not cover exactly the classes of the dataset.')];

                continue;
            }
            $seen[$key] = true;
            $course = $courses[$d['course_id']];

            if (($lecturerOf["{$d['course_id']}:{$d['class_number']}"] ?? null) !== $d['lecturer_id']) {
                $errors["schedule_details.{$i}.lecturer_id"] = [__('The lecturer does not match the teaching assignment.')];
                $hard['HC1']++;
            }
            if (! isset($slotIds[$d['time_slot_id']])) {
                $errors["schedule_details.{$i}.time_slot_id"] = [__('Unknown time slot.')];
            }
            if ($d['room_id'] !== null) {
                $room = $rooms[$d['room_id']] ?? null;
                $use = "{$d['time_slot_id']}:{$d['room_id']}";
                if ($room === null) {
                    $errors["schedule_details.{$i}.room_id"] = [__('The room cannot be used by this faculty.')];
                } elseif ($room['capacity'] < $course['class_capacity']) {
                    $errors["schedule_details.{$i}.room_id"] = [__('The room is too small for the class.')];
                } elseif (isset($roomUse[$use]) || isset($occupied[$use])) {
                    $errors["schedule_details.{$i}.room_id"] = [__('The room is already used at this time.')];
                }
                $roomUse[$use] = true;
            }

            $slot = $d['time_slot_id'];
            $lecturerSlot["{$d['lecturer_id']}:{$slot}"] = ($lecturerSlot["{$d['lecturer_id']}:{$slot}"] ?? 0) + 1;
            $cohort = "{$course['study_program_id']}:{$course['semester']}:{$d['class_number']}:{$slot}";
            $cohortSlot[$cohort] = ($cohortSlot[$cohort] ?? 0) + 1;
            $program = "{$course['study_program_id']}:{$slot}";
            $programSlot[$program] = ($programSlot[$program] ?? 0) + 1;
        }

        if (count($seen) !== count($expected)) {
            $errors['schedule_details'] = [__('The schedule does not cover exactly the classes of the dataset.')];
        }

        $excess = fn (array $counts) => array_sum(array_map(fn (int $c) => max(0, $c - 1), $counts));
        $hard['HC2'] = $excess($lecturerSlot);
        $hard['HC3'] = $excess($cohortSlot);
        $hard['HC4'] = count(array_filter($programSlot, fn (int $c) => $c > $parallelClassLimit));

        return ['errors' => $errors, 'hard' => $hard];
    }
}
