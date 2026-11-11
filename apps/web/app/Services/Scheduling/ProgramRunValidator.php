<?php

namespace App\Services\Scheduling;

use App\Exceptions\Shared\ApiException;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;

/**
 * Re-checks a study program run against the data as it is now, before it is activated or handed
 * to the faculty. Since the run was computed, the faculty may have moved cells, another program
 * may have activated a run with a shared lecturer, or TPB may have published. Every check is hard:
 *
 *   - cell: every meeting sits in a cell the program still holds, and the room fits the class,
 *   - TPB: the room is not TPB's, the lecturer has no TPB class there, the slot is not a TPB slot
 *     of the program and semester,
 *   - lecturer: not twice in the slot, in this run or in the active run of another program,
 *   - class: one class of one semester does not meet twice in a slot.
 */
readonly class ProgramRunValidator
{
    public function __construct(private TpbOccupancyResolver $tpb) {}

    /**
     * Counts per check, all zero when the run may go ahead.
     *
     * @return array{cell: int, capacity: int, tpb: int, lecturer: int, class: int}
     */
    public function problems(SchedulingRun $run): array
    {
        $program = $run->studyProgram;
        $tpb = $this->tpb->forFaculty($program->faculty_id, $run->period);
        $tpbCells = $this->tpb->cells($program->faculty_id, $run->period);
        $cells = RoomSlotAllocation::query()
            ->where('study_program_id', $program->id)
            ->where('period', $run->period)
            ->get(['time_slot_id', 'room_id'])
            ->mapWithKeys(fn (RoomSlotAllocation $a) => ["{$a->time_slot_id}:{$a->room_id}" => true]);

        $genes = $run->scheduleDetails()
            ->with(['course:id,semester,class_capacity', 'room:id,capacity'])
            ->get(['id', 'course_id', 'class_number', 'lecturer_id', 'time_slot_id', 'room_id']);
        $others = ScheduleDetail::query()
            ->join('scheduling_runs', 'scheduling_runs.id', '=', 'schedule_details.scheduling_run_id')
            ->where('scheduling_runs.is_active', true)
            ->where('scheduling_runs.period', $run->period)
            ->whereNotNull('scheduling_runs.faculty_id')
            ->where(fn ($q) => $q->whereNull('scheduling_runs.study_program_id')->orWhere('scheduling_runs.study_program_id', '!=', $program->id))
            ->whereIn('schedule_details.lecturer_id', $genes->pluck('lecturer_id')->unique())
            ->get(['schedule_details.lecturer_id', 'schedule_details.time_slot_id'])
            ->mapWithKeys(fn (ScheduleDetail $d) => ["{$d->lecturer_id}:{$d->time_slot_id}" => true]);

        $problems = ['cell' => 0, 'capacity' => 0, 'tpb' => 0, 'lecturer' => 0, 'class' => 0];
        $lecturerSlot = [];
        $classSlot = [];
        foreach ($genes as $gene) {
            $slot = $gene->time_slot_id;
            if ($gene->room_id === null || ! isset($cells["{$slot}:{$gene->room_id}"])) {
                $problems['cell']++;
            } elseif ($gene->room->capacity < $gene->course->class_capacity) {
                $problems['capacity']++;
            }
            if (($gene->room_id !== null && isset($tpbCells["{$slot}:{$gene->room_id}"]))
                || in_array($slot, $tpb['lecturers'][$gene->lecturer_id] ?? [], true)
                || in_array($slot, $tpb['programs'][$program->id][$gene->course->semester] ?? [], true)) {
                $problems['tpb']++;
            }
            if (isset($others["{$gene->lecturer_id}:{$slot}"])) {
                $problems['lecturer']++;
            }
            $lecturerSlot["{$gene->lecturer_id}:{$slot}"] = ($lecturerSlot["{$gene->lecturer_id}:{$slot}"] ?? 0) + 1;
            $class = "{$gene->course->semester}:{$gene->class_number}:{$slot}";
            $classSlot[$class] = ($classSlot[$class] ?? 0) + 1;
        }
        $excess = fn (array $counts) => array_sum(array_map(fn (int $c) => max(0, $c - 1), $counts));
        $problems['lecturer'] += $excess($lecturerSlot);
        $problems['class'] += $excess($classSlot);

        return $problems;
    }

    /**
     * Throws when any check fails, naming what to fix.
     */
    public function assertValid(SchedulingRun $run): void
    {
        $problems = array_filter($this->problems($run));
        if ($problems === []) {
            return;
        }

        $labels = [
            'cell' => __('meetings outside the program\'s room slots'),
            'capacity' => __('rooms too small for the class'),
            'tpb' => __('meetings on TPB rooms, lecturers or slots'),
            'lecturer' => __('lecturer clashes'),
            'class' => __('class clashes'),
        ];
        $parts = array_map(fn (string $key, int $count) => "{$count} {$labels[$key]}", array_keys($problems), $problems);

        throw ApiException::invalidState(__('The schedule no longer fits the current data: :problems. Move the meetings or run the GA again.', ['problems' => implode(', ', $parts)]));
    }
}
