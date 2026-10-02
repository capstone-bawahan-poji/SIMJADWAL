<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\DepartmentFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\Department\ListDepartmentRequest;
use App\Http\Requests\MasterData\Department\StoreDepartmentRequest;
use App\Http\Requests\MasterData\Department\UpdateDepartmentRequest;
use App\Models\MasterData\Department;
use App\Services\MasterData\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DepartmentController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Department::class, only: ['index']),
            new Middleware('can:view,department', only: ['show']),
            new Middleware('can:create,'.Department::class, only: ['store']),
            new Middleware('can:update,department', only: ['update']),
            new Middleware('can:delete,department', only: ['destroy']),
        ];
    }

    public function __construct(private readonly DepartmentService $departmentService) {}

    public function index(ListDepartmentRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->departmentService->getDepartments(
            search: $v['q'] ?? null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
        ));
    }

    public function show(Department $department): JsonResponse
    {
        return $this->response($this->departmentService->getDepartment($department));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        return $this->response($this->departmentService->createDepartment(DepartmentFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        return $this->response($this->departmentService->updateDepartment($department, DepartmentFormData::fromInput($request->validated(), $department)));
    }

    public function destroy(Department $department): Response
    {
        $this->departmentService->deleteDepartment($department);

        return $this->noContent();
    }
}
