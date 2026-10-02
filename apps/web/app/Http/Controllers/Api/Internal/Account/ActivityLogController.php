<?php

namespace App\Http\Controllers\Api\Internal\Account;

use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\Account\ActivityLog\ListActivityLogRequest;
use App\Models\Account\ActivityLog;
use App\Services\Account\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Read-only audit trail, newest first.
 */
class ActivityLogController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.ActivityLog::class, only: ['index']),
        ];
    }

    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(ListActivityLogRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->activityLogService->getActivityLogs(
            event: $v['event'] ?? null,
            causerId: isset($v['causer_id']) ? (int) $v['causer_id'] : null,
            userId: isset($v['user_id']) ? (int) $v['user_id'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }
}
