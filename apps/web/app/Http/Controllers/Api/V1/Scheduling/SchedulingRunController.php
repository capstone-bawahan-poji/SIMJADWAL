<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Http\Controllers\Api\Internal\Scheduling\SchedulingRunController as InternalSchedulingRunController;
use App\Http\Requests\Scheduling\StoreSchedulingRunRequest;
use App\Models\MasterData\Faculty;
use App\Services\Scheduling\SchedulingRunUploadService;
use Illuminate\Http\JsonResponse;

/**
 * Reads as the internal endpoint. Adds the upload, which only the desktop app performs.
 */
class SchedulingRunController extends InternalSchedulingRunController
{
    public function store(StoreSchedulingRunRequest $request, SchedulingRunUploadService $uploadService): JsonResponse
    {
        $result = $uploadService->store(
            $this->request->user(),
            Faculty::query()->findOrFail($request->facultyId()),
            [...$request->validated(), 'parameter_snapshot' => $request->input('parameter_snapshot')],
        );

        return $this->response($this->queryService->getRun($result['run']), $result['created'] ? 201 : 200);
    }
}
