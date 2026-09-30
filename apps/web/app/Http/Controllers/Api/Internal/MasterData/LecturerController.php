<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\LecturerFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\MasterData\Lecturer\ListLecturerRequest;
use App\Http\Requests\MasterData\Lecturer\StoreLecturerRequest;
use App\Http\Requests\MasterData\Lecturer\UpdateLecturerRequest;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Services\MasterData\LecturerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LecturerController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Lecturer::class, only: ['index']),
            new Middleware('can:view,lecturer', only: ['show']),
            new Middleware('can:update,lecturer', only: ['update']),
            new Middleware('can:delete,lecturer', only: ['destroy']),
        ];
    }

    public function __construct(private readonly LecturerService $lecturerService) {}

    public function index(ListLecturerRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->lecturerService->getLecturers(
            search: $v['q'] ?? null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(Lecturer $lecturer): JsonResponse
    {
        return $this->response($this->lecturerService->getLecturer($lecturer));
    }

    public function store(StoreLecturerRequest $request): JsonResponse
    {
        $data = LecturerFormData::fromInput($request->validated());
        $this->authorize('create', [Lecturer::class, StudyProgram::query()->findOrFail($data->studyProgramId)]);

        return $this->response($this->lecturerService->createLecturer($data), 201);
    }

    public function update(UpdateLecturerRequest $request, Lecturer $lecturer): JsonResponse
    {
        $data = LecturerFormData::fromInput($request->validated(), $lecturer);

        if ($data->studyProgramId !== $lecturer->study_program_id) {
            $this->authorize('create', [Lecturer::class, StudyProgram::query()->findOrFail($data->studyProgramId)]);
        }

        return $this->response($this->lecturerService->updateLecturer($lecturer, $data));
    }

    public function destroy(Lecturer $lecturer): Response
    {
        $this->lecturerService->deleteLecturer($lecturer);

        return $this->noContent();
    }
}
