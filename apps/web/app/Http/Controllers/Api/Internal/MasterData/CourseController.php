<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\CourseFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\MasterData\Course\ListCourseRequest;
use App\Http\Requests\MasterData\Course\StoreCourseRequest;
use App\Http\Requests\MasterData\Course\UpdateCourseRequest;
use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Services\MasterData\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Regular courses (per study program) and TPB courses (is_tpb, no program) share this endpoint.
 */
class CourseController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Course::class, only: ['index']),
            new Middleware('can:view,course', only: ['show']),
            new Middleware('can:update,course', only: ['update']),
            new Middleware('can:delete,course', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly Request $request,
        private readonly CourseService $courseService,
    ) {}

    public function index(ListCourseRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->courseService->getCourses(
            actor: $this->request->user(),
            search: $v['q'] ?? null,
            isTpb: isset($v['is_tpb']) ? (bool) $v['is_tpb'] : null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            semester: isset($v['semester']) ? (int) $v['semester'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(Course $course): JsonResponse
    {
        return $this->response($this->courseService->getCourse($course));
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = CourseFormData::fromInput($request->validated());
        $studyProgram = $data->isTpb ? null : StudyProgram::query()->findOrFail($data->studyProgramId);
        $this->authorize('create', [Course::class, $studyProgram]);

        return $this->response($this->courseService->createCourse($data), 201);
    }

    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        return $this->response($this->courseService->updateCourse($course, CourseFormData::fromInput($request->validated(), $course)));
    }

    public function destroy(Course $course): Response
    {
        $this->courseService->deleteCourse($course);

        return $this->noContent();
    }
}
