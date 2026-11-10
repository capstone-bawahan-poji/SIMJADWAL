<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Exceptions\Shared\ApiException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Catalog\ListCatalogCoursesRequest;
use App\Http\Requests\Catalog\SendScheduleEmailRequest;
use App\Jobs\SendPublishedSchedule;
use App\Services\Catalog\CatalogService;
use App\Services\Scheduling\ScheduleQueryService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated, read-only. Feeds the landing page where students pick their classes.
 */
class CatalogController extends ApiController
{
    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly ScheduleQueryService $scheduleQueryService,
    ) {}

    public function faculties(): JsonResponse
    {
        return $this->response($this->catalogService->faculties()->all());
    }

    public function courses(ListCatalogCoursesRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->catalogService->courses(
            search: $v['q'] ?? null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            semester: isset($v['semester']) ? (int) $v['semester'] : null,
            perPage: (int) ($v['per_page'] ?? ListCatalogCoursesRequest::DEFAULT_PER_PAGE),
        ));
    }

    /**
     * Queues the personal schedule of the picked classes. Only published schedules are sent.
     */
    public function sendSchedule(SendScheduleEmailRequest $request): JsonResponse
    {
        $period = config('scheduling.current_period');
        $classes = $request->classes();

        $runIds = $this->scheduleQueryService->publishedRuns($period)->pluck('id')->all();

        if (! $this->scheduleQueryService->details($runIds, ['classes' => $classes])->exists()) {
            throw ApiException::validation(['classes' => [__('The classes you picked have no published schedule yet.')]]);
        }

        SendPublishedSchedule::dispatch($request->validated('email'), $period, $classes, $request->formats());

        return $this->response(['queued' => true], 202);
    }
}
