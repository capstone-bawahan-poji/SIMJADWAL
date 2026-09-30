<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\FacultyFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\Faculty\StoreFacultyRequest;
use App\Http\Requests\MasterData\Faculty\UpdateFacultyRequest;
use App\Models\MasterData\Faculty;
use App\Services\MasterData\FacultyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FacultyController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Faculty::class, only: ['index']),
            new Middleware('can:view,faculty', only: ['show']),
            new Middleware('can:create,'.Faculty::class, only: ['store']),
            new Middleware('can:update,faculty', only: ['update']),
            new Middleware('can:delete,faculty', only: ['destroy']),
        ];
    }

    public function __construct(private readonly FacultyService $facultyService) {}

    public function index(): JsonResponse
    {
        return $this->response($this->facultyService->getFaculties());
    }

    public function show(Faculty $faculty): JsonResponse
    {
        return $this->response($this->facultyService->getFaculty($faculty));
    }

    public function store(StoreFacultyRequest $request): JsonResponse
    {
        return $this->response($this->facultyService->createFaculty(FacultyFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateFacultyRequest $request, Faculty $faculty): JsonResponse
    {
        return $this->response($this->facultyService->updateFaculty($faculty, FacultyFormData::fromInput($request->validated(), $faculty)));
    }

    public function destroy(Faculty $faculty): Response
    {
        $this->facultyService->deleteFaculty($faculty);

        return $this->noContent();
    }
}
