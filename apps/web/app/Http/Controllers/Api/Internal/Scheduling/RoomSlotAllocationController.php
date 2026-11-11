<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\FacultyPeriodRequest;
use App\Http\Requests\Scheduling\PeriodRequest;
use App\Http\Requests\Scheduling\SyncRoomSlotsRequest;
use App\Models\MasterData\StudyProgram;
use App\Services\Scheduling\DemoSlotFillService;
use App\Services\Scheduling\RoomSlotAllocationService;
use App\Services\Scheduling\SlotSufficiencyService;
use Illuminate\Http\JsonResponse;

/**
 * Cells (period, slot, room) the faculty hands to its study programs, and whether a program's
 * cells hold its classes.
 */
class RoomSlotAllocationController extends ApiController
{
    public function __construct(
        protected readonly RoomSlotAllocationService $allocations,
        protected readonly SlotSufficiencyService $sufficiency,
        protected readonly DemoSlotFillService $demoFill,
    ) {}

    public function index(FacultyPeriodRequest $request): JsonResponse
    {
        $faculty = $request->faculty();
        $this->authorize('viewRoomSlots', $faculty);

        return $this->response($this->withSufficiency($this->allocations->grid($faculty, $request->period())));
    }

    public function sync(SyncRoomSlotsRequest $request): JsonResponse
    {
        $faculty = $request->faculty();
        $this->authorize('manageRoomSlots', $faculty);
        $period = $request->validated('period');

        return $this->response([
            ...$this->allocations->sync($request->user(), $faculty, $period, $request->cells()),
            'grid' => $this->withSufficiency($this->allocations->grid($faculty, $period)),
        ]);
    }

    /**
     * Demo only (404 in production): gives every program the cells it is short of.
     */
    public function autoFill(FacultyPeriodRequest $request): JsonResponse
    {
        abort_if(app()->isProduction(), 404);
        $faculty = $request->faculty();
        $this->authorize('manageRoomSlots', $faculty);
        $period = $request->period();

        return $this->response([
            'programs' => $this->demoFill->fill($request->user(), $faculty, $period),
            'grid' => $this->withSufficiency($this->allocations->grid($faculty, $period)),
        ]);
    }

    /**
     * Adds whether each program's cells hold its classes, so the faculty sees who is short.
     *
     * @param  array<string, mixed>  $grid
     * @return array<string, mixed>
     */
    private function withSufficiency(array $grid): array
    {
        $programs = StudyProgram::query()->whereIn('id', array_column($grid['study_programs'], 'id'))->get()->keyBy('id');
        $grid['study_programs'] = array_map(function (array $row) use ($programs, $grid) {
            $check = $this->sufficiency->check($programs[$row['id']], $grid['period']);

            return [...$row, 'required_meetings' => $check['required_meetings'], 'required_by_type' => $check['required_by_type'], 'sufficient' => $check['sufficient']];
        }, $grid['study_programs']);

        return $grid;
    }

    public function program(PeriodRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        $this->authorize('viewRoomSlots', $studyProgram);
        $period = $request->period();

        return $this->response([
            'period' => $period,
            'cells' => $this->allocations->cellsOf($studyProgram, $period),
            'sufficiency' => $this->sufficiency->check($studyProgram, $period),
        ]);
    }
}
