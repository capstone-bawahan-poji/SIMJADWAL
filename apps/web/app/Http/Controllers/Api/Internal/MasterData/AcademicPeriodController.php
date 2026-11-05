<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\AcademicPeriodFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\AcademicPeriod\StoreAcademicPeriodRequest;
use App\Http\Requests\MasterData\AcademicPeriod\UpdateAcademicPeriodRequest;
use App\Models\MasterData\AcademicPeriod;
use App\Services\MasterData\AcademicPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AcademicPeriodController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.AcademicPeriod::class, only: ['index']),
            new Middleware('can:view,academicPeriod', only: ['show']),
            new Middleware('can:create,'.AcademicPeriod::class, only: ['store']),
            new Middleware('can:update,academicPeriod', only: ['update']),
            new Middleware('can:delete,academicPeriod', only: ['destroy']),
        ];
    }

    public function __construct(private readonly AcademicPeriodService $academicPeriodService) {}

    public function index(): JsonResponse
    {
        return $this->response($this->academicPeriodService->getAcademicPeriods());
    }

    public function show(AcademicPeriod $academicPeriod): JsonResponse
    {
        return $this->response($this->academicPeriodService->getAcademicPeriod($academicPeriod));
    }

    public function store(StoreAcademicPeriodRequest $request): JsonResponse
    {
        return $this->response($this->academicPeriodService->createAcademicPeriod(AcademicPeriodFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateAcademicPeriodRequest $request, AcademicPeriod $academicPeriod): JsonResponse
    {
        return $this->response($this->academicPeriodService->updateAcademicPeriod($academicPeriod, AcademicPeriodFormData::fromInput($request->validated(), $academicPeriod)));
    }

    public function destroy(AcademicPeriod $academicPeriod): Response
    {
        $this->academicPeriodService->deleteAcademicPeriod($academicPeriod);

        return $this->noContent();
    }
}