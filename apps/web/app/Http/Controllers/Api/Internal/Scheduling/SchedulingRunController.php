<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Enums\Constraint\ConstraintCategory;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\Scheduling\ListSchedulingRunRequest;
use App\Models\Scheduling\SchedulingRun;
use App\Services\Scheduling\SchedulingRunActivationService;
use App\Services\Scheduling\SchedulingRunQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SchedulingRunController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.SchedulingRun::class, only: ['index']),
            new Middleware('can:view,schedulingRun', only: ['show', 'scheduleDetails', 'violations']),
            new Middleware('can:activate,schedulingRun', only: ['activate']),
            new Middleware('can:publish,schedulingRun', only: ['publish']),
        ];
    }

    public function __construct(
        protected readonly Request $request,
        protected readonly SchedulingRunQueryService $queryService,
        protected readonly SchedulingRunActivationService $activationService,
    ) {}

    public function index(ListSchedulingRunRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->queryService->getRuns(
            actor: $this->request->user(),
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            period: $v['period'] ?? null,
            batchId: $v['batch_id'] ?? null,
            isActive: isset($v['is_active']) ? $request->boolean('is_active') : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(SchedulingRun $schedulingRun): JsonResponse
    {
        return $this->response($this->queryService->getRun($schedulingRun));
    }

    public function scheduleDetails(SchedulingRun $schedulingRun): JsonResponse
    {
        $v = $this->request->validate([
            'study_program_id' => ['nullable', 'integer'],
            'day' => ['nullable', 'integer', 'between:1,7'],
        ]);

        return $this->response($this->queryService->getScheduleDetails(
            $schedulingRun,
            isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            isset($v['day']) ? (int) $v['day'] : null,
        ));
    }

    public function violations(SchedulingRun $schedulingRun): JsonResponse
    {
        $v = $this->request->validate(['category' => ['nullable', 'in:HC,SC']]);

        return $this->response($this->queryService->getViolations(
            $schedulingRun,
            isset($v['category']) ? ConstraintCategory::from($v['category']) : null,
        ));
    }

    public function activate(SchedulingRun $schedulingRun): JsonResponse
    {
        $run = $this->activationService->activate($this->request->user(), $schedulingRun);

        return $this->response($this->queryService->getRun($run));
    }

    public function publish(SchedulingRun $schedulingRun): JsonResponse
    {
        $run = $this->activationService->publish($this->request->user(), $schedulingRun);

        return $this->response($this->queryService->getRun($run));
    }
}