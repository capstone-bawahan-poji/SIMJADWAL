<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\ExportRoomGridRequest;
use App\Http\Requests\Scheduling\ExportScheduleRequest;
use App\Http\Requests\Scheduling\ListScheduleRequest;
use App\Http\Requests\Scheduling\OverrideScheduleDetailRequest;
use App\Http\Requests\Scheduling\OverrideTpbBlockRequest;
use App\Http\Requests\Scheduling\RoomGridRequest;
use App\Http\Requests\Scheduling\SwapScheduleDetailsRequest;
use App\Models\Scheduling\ScheduleDetail;
use App\Services\Scheduling\Export\RoomGridTransformer;
use App\Services\Scheduling\Export\ScheduleExportService;
use App\Services\Scheduling\ScheduleDetailTransformer;
use App\Services\Scheduling\ScheduleOverrideService;
use App\Services\Scheduling\ScheduleQueryService;
use App\Services\Scheduling\TpbOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The official (active) schedule of a period, for every role: view, export, and manual overrides.
 */
class ScheduleController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:override,scheduleDetail', only: ['options', 'override', 'tpbOptions', 'tpbOverride']),
        ];
    }

    public function __construct(
        protected readonly ScheduleQueryService $queryService,
        protected readonly ScheduleOverrideService $overrideService,
        protected readonly TpbOverrideService $tpbOverrideService,
        protected readonly ScheduleExportService $exportService,
    ) {}

    public function index(ListScheduleRequest $request): JsonResponse
    {
        return $this->response($this->queryService->getSchedule($request->user(), $request->period(), $request->filters()));
    }

    public function export(ExportScheduleRequest $request): Response
    {
        return $this->exportService->export($request->user(), $request->period(), $request->filters(), $request->validated('format'));
    }

    public function roomGrid(RoomGridRequest $request): JsonResponse
    {
        $facultyId = $request->validated('faculty_id');
        $grid = $this->exportService->roomGrid(
            $request->user(),
            $request->period(),
            $request->validated('scope'),
            $facultyId !== null ? (int) $facultyId : null,
        );

        return $this->response($grid === null ? null : RoomGridTransformer::toArray($grid));
    }

    public function exportRoomGrid(ExportRoomGridRequest $request): Response
    {
        $facultyId = $request->validated('faculty_id');

        return $this->exportService->exportRoomGrid(
            $request->user(),
            $request->period(),
            $request->validated('scope'),
            $facultyId !== null ? (int) $facultyId : null,
            $request->validated('format'),
        );
    }

    public function options(ScheduleDetail $scheduleDetail): JsonResponse
    {
        return $this->response($this->overrideService->options($scheduleDetail));
    }

    public function override(OverrideScheduleDetailRequest $request, ScheduleDetail $scheduleDetail): JsonResponse
    {
        $detail = $this->overrideService->override(
            $request->user(),
            $scheduleDetail,
            (int) $request->validated('time_slot_id'),
            (int) $request->validated('room_id'),
            $request->validated('reason'),
        );

        return $this->response(ScheduleDetailTransformer::toArray($detail->fresh(ScheduleDetailTransformer::relations())));
    }

    public function swap(SwapScheduleDetailsRequest $request): JsonResponse
    {
        $a = $request->detailA();
        $b = $request->detailB();
        $this->authorize('override', $a);
        $this->authorize('override', $b);

        $details = $this->overrideService->swap($request->user(), $a, $b, $request->validated('reason'));

        return $this->response(array_map(
            fn ($detail) => ScheduleDetailTransformer::toArray($detail->fresh(ScheduleDetailTransformer::relations())),
            $details,
        ));
    }

    public function tpbOptions(ScheduleDetail $scheduleDetail): JsonResponse
    {
        return $this->response($this->tpbOverrideService->options($scheduleDetail));
    }

    public function tpbOverride(OverrideTpbBlockRequest $request, ScheduleDetail $scheduleDetail): JsonResponse
    {
        $block = $this->tpbOverrideService->override($request->user(), $scheduleDetail, (int) $request->validated('time_slot_id'), $request->validated('reason'));

        return $this->response($this->transformed($block->pluck('id')->all()));
    }

    public function tpbSwap(SwapScheduleDetailsRequest $request): JsonResponse
    {
        $a = $request->detailA();
        $b = $request->detailB();
        $this->authorize('override', $a);
        $this->authorize('override', $b);

        $details = $this->tpbOverrideService->swap($request->user(), $a, $b, $request->validated('reason'));

        return $this->response($this->transformed($details->pluck('id')->all()));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function transformed(array $ids): array
    {
        return ScheduleDetail::query()->with(ScheduleDetailTransformer::relations())->whereKey($ids)->orderBy('id')->get()
            ->map(fn (ScheduleDetail $detail) => ScheduleDetailTransformer::toArray($detail))
            ->all();
    }
}
