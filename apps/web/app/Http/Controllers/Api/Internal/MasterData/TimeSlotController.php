<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\TimeSlotFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\TimeSlot\StoreTimeSlotRequest;
use App\Http\Requests\MasterData\TimeSlot\UpdateTimeSlotRequest;
use App\Models\MasterData\TimeSlot;
use App\Services\MasterData\TimeSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TimeSlotController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.TimeSlot::class, only: ['index']),
            new Middleware('can:view,timeSlot', only: ['show']),
            new Middleware('can:create,'.TimeSlot::class, only: ['store']),
            new Middleware('can:update,timeSlot', only: ['update']),
            new Middleware('can:delete,timeSlot', only: ['destroy']),
        ];
    }

    public function __construct(private readonly TimeSlotService $timeSlotService) {}

    public function index(): JsonResponse
    {
        return $this->response($this->timeSlotService->getTimeSlots());
    }

    public function show(TimeSlot $timeSlot): JsonResponse
    {
        return $this->response($this->timeSlotService->getTimeSlot($timeSlot));
    }

    public function store(StoreTimeSlotRequest $request): JsonResponse
    {
        return $this->response($this->timeSlotService->createTimeSlot(TimeSlotFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateTimeSlotRequest $request, TimeSlot $timeSlot): JsonResponse
    {
        return $this->response($this->timeSlotService->updateTimeSlot($timeSlot, TimeSlotFormData::fromInput($request->validated(), $timeSlot)));
    }

    public function destroy(TimeSlot $timeSlot): Response
    {
        $this->timeSlotService->deleteTimeSlot($timeSlot);

        return $this->noContent();
    }
}
