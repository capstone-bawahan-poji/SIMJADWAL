<?php

namespace App\Http\Controllers\Api\Internal\Constraint;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Constraint\UpdateConstraintWeightRequest;
use App\Models\Constraint\ConstraintType;
use App\Services\Constraint\ConstraintTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ConstraintTypeController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.ConstraintType::class, only: ['index']),
            new Middleware('can:update,constraintType', only: ['update']),
        ];
    }

    public function __construct(private readonly ConstraintTypeService $constraintTypeService) {}

    public function index(): JsonResponse
    {
        return $this->response($this->constraintTypeService->getConstraintTypes());
    }

    public function update(UpdateConstraintWeightRequest $request, ConstraintType $constraintType): JsonResponse
    {
        return $this->response($this->constraintTypeService->updateWeight($constraintType, (float) $request->validated('weight')));
    }
}
