<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\Room;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Services\MasterData\TpbBlockService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Loads what the hard-constraint checks need in a few queries, so the checks themselves
 * run in memory: every active gene of the period, the TPB blocks and the candidate rooms.
 */
readonly class ScheduleSlotCheckerFactory
{
    public function __construct(private TpbBlockService $tpbBlocks) {}

    public function for(ScheduleDetail $detail): ScheduleSlotChecker
    {
        $run = $detail->schedulingRun;
        $course = $detail->course;
        $odd = Period::isOdd($run->period);

        $genes = $this->activeGenes($run->period)->whereKeyNot($detail->id)->get();
        $sameProgram = $genes->filter(fn (ScheduleDetail $g) => $g->scheduling_run_id === $run->id
            && $g->course->study_program_id === $course->study_program_id);

        return new ScheduleSlotChecker(
            detail: $detail,
            occupancy: new ActiveScheduleOccupancy($genes),
            programCount: $sameProgram->countBy('time_slot_id')->all(),
            cohortAt: $sameProgram
                ->filter(fn (ScheduleDetail $g) => $g->course->semester === $course->semester && $g->class_number === $detail->class_number)
                ->keyBy('time_slot_id')->all(),
            blockedForProgram: $this->tpbBlocks->blockedSlotsByStudyProgram($odd)[$course->study_program_id][$course->semester] ?? [],
            blockedForLecturer: $this->tpbBlocks->blockedSlotsByLecturer($odd)[$detail->lecturer_id] ?? [],
            blockedRooms: $this->tpbBlocks->blockedSlotsByRoom($odd),
            candidateRooms: Room::query()
                ->where(fn ($q) => $q->where('faculty_id', $run->faculty_id)->orWhereNull('faculty_id'))
                ->where('capacity', '>=', $course->class_capacity)
                ->orderBy('capacity')->orderBy('code')
                ->get(['id', 'code', 'name', 'capacity', 'faculty_id']),
            parallelLimit: (int) ($run->parameter_snapshot['parallel_class_limit'] ?? PHP_INT_MAX),
        );
    }

    /**
     * Lecturers and rooms held by the active schedules of the other faculties in the run's period.
     */
    public function otherFacultiesOccupancy(SchedulingRun $run): ActiveScheduleOccupancy
    {
        return new ActiveScheduleOccupancy(
            $this->activeGenes($run->period)
                ->whereHas('schedulingRun', fn (Builder $q) => $q->where('faculty_id', '!=', $run->faculty_id))
                ->get(),
        );
    }

    /**
     * @return Builder<ScheduleDetail>
     */
    private function activeGenes(string $period): Builder
    {
        return ScheduleDetail::query()
            ->with('course:id,name,study_program_id,semester')
            ->whereHas('schedulingRun', fn (Builder $q) => $q->where('is_active', true)->where('period', $period))
            ->select(['id', 'scheduling_run_id', 'course_id', 'class_number', 'lecturer_id', 'time_slot_id', 'room_id']);
    }
}