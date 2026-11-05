<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\ShowSchedulingDatasetRequest;
use App\Models\MasterData\Faculty;
use App\Services\Scheduling\SchedulingDatasetService;
use Illuminate\Http\JsonResponse;

class SchedulingDatasetController extends ApiController
{
    public function __construct(private readonly SchedulingDatasetService $datasetService) {}

    public function show(ShowSchedulingDatasetRequest $request): JsonResponse
    {
        return $this->response($this->datasetService->build(
            faculty: Faculty::query()->findOrFail($request->facultyId()),
            period: $request->period(),
        ));
    }
}
