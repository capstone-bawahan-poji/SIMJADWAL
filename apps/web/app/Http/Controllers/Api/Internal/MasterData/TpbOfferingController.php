<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\TpbOfferingFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\MasterData\TpbOffering\ListTpbOfferingRequest;
use App\Http\Requests\MasterData\TpbOffering\StoreTpbOfferingRequest;
use App\Http\Requests\MasterData\TpbOffering\UpdateTpbOfferingRequest;
use App\Models\MasterData\TpbOffering;
use App\Services\MasterData\TpbOfferingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TpbOfferingController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.TpbOffering::class, only: ['index']),
            new Middleware('can:view,tpbOffering', only: ['show']),
            new Middleware('can:create,'.TpbOffering::class, only: ['store']),
            new Middleware('can:update,tpbOffering', only: ['update']),
            new Middleware('can:delete,tpbOffering', only: ['destroy']),
        ];
    }

    public function __construct(private readonly TpbOfferingService $tpbOfferingService) {}

    public function index(ListTpbOfferingRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->tpbOfferingService->getOfferings(
            period: $v['period'] ?? null,
            courseId: isset($v['course_id']) ? (int) $v['course_id'] : null,
            search: $v['q'] ?? null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(TpbOffering $tpbOffering): JsonResponse
    {
        return $this->response($this->tpbOfferingService->getOffering($tpbOffering));
    }

    public function store(StoreTpbOfferingRequest $request): JsonResponse
    {
        return $this->response($this->tpbOfferingService->createOffering(TpbOfferingFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateTpbOfferingRequest $request, TpbOffering $tpbOffering): JsonResponse
    {
        return $this->response($this->tpbOfferingService->updateOffering($tpbOffering, TpbOfferingFormData::fromInput($request->validated(), $tpbOffering)));
    }

    public function destroy(TpbOffering $tpbOffering): Response
    {
        $this->tpbOfferingService->deleteOffering($tpbOffering);

        return $this->noContent();
    }
}
