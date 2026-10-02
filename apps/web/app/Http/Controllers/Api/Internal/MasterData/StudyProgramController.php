<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\StudyProgramFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MasterData\StudyProgram\ListStudyProgramRequest;
use App\Http\Requests\MasterData\StudyProgram\StoreStudyProgramRequest;
use App\Http\Requests\MasterData\StudyProgram\UpdateStudyProgramRequest;
use App\Models\MasterData\StudyProgram;
use App\Services\MasterData\StudyProgramService;
use App\Services\MasterData\TpbGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StudyProgramController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.StudyProgram::class, only: ['index']),
            new Middleware('can:view,studyProgram', only: ['show', 'tpbBlockedSlots']),
            new Middleware('can:create,'.StudyProgram::class, only: ['store']),
            new Middleware('can:update,studyProgram', only: ['update']),
            new Middleware('can:delete,studyProgram', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly StudyProgramService $studyProgramService,
        private readonly TpbGroupService $tpbGroupService,
    ) {}

    public function index(ListStudyProgramRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->studyProgramService->getStudyPrograms(
            search: $v['q'] ?? null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            departmentId: isset($v['department_id']) ? (int) $v['department_id'] : null,
        ));
    }

    public function show(StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->studyProgramService->getStudyProgram($studyProgram));
    }

    public function store(StoreStudyProgramRequest $request): JsonResponse
    {
        return $this->response($this->studyProgramService->createStudyProgram(StudyProgramFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateStudyProgramRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->studyProgramService->updateStudyProgram(
            $studyProgram,
            StudyProgramFormData::fromInput($request->validated(), $studyProgram),
        ));
    }

    public function destroy(StudyProgram $studyProgram): Response
    {
        $this->studyProgramService->deleteStudyProgram($studyProgram);

        return $this->noContent();
    }

    public function tpbBlockedSlots(StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->tpbGroupService->getPlacedGroupsFor($studyProgram));
    }
}
