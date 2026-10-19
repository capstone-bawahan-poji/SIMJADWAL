<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Data\Scheduling\GaPresetFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\ListGaPresetRequest;
use App\Http\Requests\Scheduling\StoreGaPresetRequest;
use App\Http\Requests\Scheduling\UpdateGaPresetRequest;
use App\Models\Scheduling\GaParameterPreset;
use App\Services\Scheduling\GaPresetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GaPresetController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.GaParameterPreset::class, only: ['index']),
            new Middleware('can:view,gaPreset', only: ['show']),
            new Middleware('can:update,gaPreset', only: ['update']),
            new Middleware('can:delete,gaPreset', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly Request $request,
        private readonly GaPresetService $presetService,
    ) {}

    public function index(ListGaPresetRequest $request): JsonResponse
    {
        $this->authorize('viewFaculty', [GaParameterPreset::class, $request->facultyId()]);

        return $this->response($this->presetService->getPresets($request->facultyId()));
    }

    public function show(GaParameterPreset $gaPreset): JsonResponse
    {
        return $this->response($this->presetService->getPreset($gaPreset));
    }

    public function store(StoreGaPresetRequest $request): JsonResponse
    {
        $facultyId = $request->facultyId();
        $this->authorize('create', [GaParameterPreset::class, $facultyId]);

        return $this->response($this->presetService->createPreset(
            GaPresetFormData::fromInput($request->validated(), $facultyId),
            $this->request->user(),
        ), 201);
    }

    public function update(UpdateGaPresetRequest $request, GaParameterPreset $gaPreset): JsonResponse
    {
        return $this->response($this->presetService->updatePreset(
            $gaPreset,
            GaPresetFormData::fromInput($request->validated(), $gaPreset->faculty_id),
        ));
    }

    public function destroy(GaParameterPreset $gaPreset): Response
    {
        $this->presetService->deletePreset($gaPreset);

        return $this->noContent();
    }
}
