<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\ListScheduleRequest;
use App\Http\Requests\Scheduling\OverrideScheduleDetailRequest;
use App\Models\Scheduling\ScheduleDetail;
use App\Services\Scheduling\ScheduleDetailTransformer;
use App\Services\Scheduling\ScheduleOverrideService;
use App\Services\Scheduling\ScheduleQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The official (active) schedule of a period, for every role, and manual overrides on it.
 */
class ScheduleController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:override,scheduleDetail', only: ['options', 'override']),
        ];
    }

    public function __construct(
        protected readonly ScheduleQueryService $queryService,
        protected readonly ScheduleOverrideService $overrideService,
    ) {}

    public function index(ListScheduleRequest $request): JsonResponse
    {
        return $this->response($this->queryService->getSchedule($request->user(), $request->period(), $request->filters()));
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
            $request->validated('room_id') !== null ? (int) $request->validated('room_id') : null,
        );

        return $this->response(ScheduleDetailTransformer::toArray($detail->fresh(ScheduleDetailTransformer::relations())));
    }
}