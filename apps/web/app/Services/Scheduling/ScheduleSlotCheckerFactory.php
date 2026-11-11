<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\Room;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Loads what the hard-constraint checks of one program run need in a few queries (every active
 * gene of the period, the TPB data, the program's cells), then builds checkers in memory. A swap
 * needs a checker per gene, so the loaded data is kept per run.
 */
class ScheduleSlotCheckerFactory
{
    /** @var array<int, array{genes: Collection<int, ScheduleDetail>, tpb: array<string, mixed>, cells: array<int, Collection<int, Room>>}> */
    private array $contexts = [];

    public function __construct(private readonly TpbOccupancyResolver $tpb) {}

    /**
     * @param  list<int>  $ignore  genes treated as gone, e.g. the other side of a swap
     */
    public function for(ScheduleDetail $detail, array $ignore = []): ScheduleSlotChecker
    {
        $run = $detail->schedulingRun;
        $context = $this->context($run);
        $course = $detail->course;
        $skip = array_flip([$detail->id, ...$ignore]);
        $genes = $context['genes']->reject(fn (ScheduleDetail $g) => isset($skip[$g->id]));
        $tpb = $context['tpb'];

        return new ScheduleSlotChecker(
            detail: $detail,
            occupancy: new ActiveScheduleOccupancy($genes),
            cohortAt: $genes
                ->filter(fn (ScheduleDetail $g) => $g->scheduling_run_id === $run->id
                    && $g->course->semester === $course->semester
                    && $g->class_number === $detail->class_number)
                ->keyBy('time_slot_id')->all(),
            blockedForProgram: $tpb['programs'][$run->study_program_id][$course->semester] ?? [],
            blockedForLecturer: $tpb['lecturers'][$detail->lecturer_id] ?? [],
            blockedRooms: $tpb['rooms'],
            cellRooms: $context['cells'],
        );
    }

    /**
     * Every gene of the run, loaded with what the checks read.
     *
     * @return Collection<int, ScheduleDetail>
     */
    public function genesOf(SchedulingRun $run): Collection
    {
        return $this->context($run)['genes']->where('scheduling_run_id', $run->id)->values();
    }

    public function forget(): void
    {
        $this->contexts = [];
        $this->tpb->forget();
    }

    /**
     * @return array{genes: Collection<int, ScheduleDetail>, tpb: array<string, mixed>, cells: array<int, Collection<int, Room>>}
     */
    private function context(SchedulingRun $run): array
    {
        return $this->contexts[$run->id] ??= [
            'genes' => $this->activeGenes($run->period)->get(),
            'tpb' => $this->tpb->forFaculty($run->faculty_id, $run->period),
            'cells' => RoomSlotAllocation::query()
                ->with('room:id,code,name,capacity,faculty_id')
                ->where('study_program_id', $run->study_program_id)
                ->where('period', $run->period)
                ->get()
                ->groupBy('time_slot_id')
                ->map(fn (Collection $rows) => $rows->pluck('room')->sortBy([['capacity', 'asc'], ['code', 'asc']])->values())
                ->all(),
        ];
    }

    /**
     * @return Builder<ScheduleDetail>
     */
    private function activeGenes(string $period): Builder
    {
        return ScheduleDetail::query()
            ->with(['course:id,name,study_program_id,semester,class_capacity', 'room:id,code,name,capacity,faculty_id'])
            ->whereHas('schedulingRun', fn (Builder $q) => $q->where('is_active', true)->where('period', $period))
            ->select(['id', 'scheduling_run_id', 'course_id', 'class_number', 'meeting_number', 'lecturer_id', 'time_slot_id', 'room_id']);
    }
}
