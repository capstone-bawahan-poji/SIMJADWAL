<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\NoteRequest;
use App\Http\Requests\Scheduling\PeriodRequest;
use App\Models\MasterData\Faculty;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Http\JsonResponse;

/**
 * POS step 10: the faculty publishes the schedules of all its programs. Final; only superadmin withdraws.
 */
class SchedulePublicationController extends ApiController
{
    public function __construct(protected readonly SchedulePublicationService $publications) {}

    public function show(PeriodRequest $request, Faculty $faculty): JsonResponse
    {
        $this->authorize('viewSchedules', $faculty);

        return $this->response($this->publications->show($faculty, $request->period()));
    }

    public function store(PeriodRequest $request, Faculty $faculty): JsonResponse
    {
        $this->authorize('publishSchedule', $faculty);

        return $this->response($this->publications->publish($request->user(), $faculty, $request->period()), 201);
    }

    public function destroy(NoteRequest $request, Faculty $faculty): JsonResponse
    {
        $this->authorize('unpublishSchedule', $faculty);

        return $this->response($this->publications->unpublish($request->user(), $faculty, $request->period(), $request->validated('note')));
    }
}
