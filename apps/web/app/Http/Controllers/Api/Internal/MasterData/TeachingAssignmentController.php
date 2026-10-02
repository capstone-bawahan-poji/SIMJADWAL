<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\TeachingAssignmentFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\MasterData\TeachingAssignment\ListTeachingAssignmentRequest;
use App\Http\Requests\MasterData\TeachingAssignment\StoreTeachingAssignmentRequest;
use App\Http\Requests\MasterData\TeachingAssignment\UpdateTeachingAssignmentRequest;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Services\MasterData\TeachingAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /teaching-assignments: one lecturer per class of a course (course_lecturers).
 */
class TeachingAssignmentController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.CourseLecturer::class, only: ['index']),
            new Middleware('can:view,courseLecturer', only: ['show']),
            new Middleware('can:update,courseLecturer', only: ['update']),
            new Middleware('can:delete,courseLecturer', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly Request $request,
        private readonly TeachingAssignmentService $assignmentService,
    ) {}

    public function index(ListTeachingAssignmentRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->assignmentService->getAssignments(
            actor: $this->request->user(),
            courseId: isset($v['course_id']) ? (int) $v['course_id'] : null,
            lecturerId: isset($v['lecturer_id']) ? (int) $v['lecturer_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            tpbGroupId: isset($v['tpb_group_id']) ? (int) $v['tpb_group_id'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(CourseLecturer $courseLecturer): JsonResponse
    {
        return $this->response($this->assignmentService->getAssignment($courseLecturer));
    }

    public function store(StoreTeachingAssignmentRequest $request): JsonResponse
    {
        $data = TeachingAssignmentFormData::fromInput($request->validated());
        $course = Course::query()->findOrFail($data->courseId);
        $this->authorize('create', [CourseLecturer::class, $course]);

        return $this->response($this->assignmentService->createAssignment($course, $data), 201);
    }

    public function update(UpdateTeachingAssignmentRequest $request, CourseLecturer $courseLecturer): JsonResponse
    {
        return $this->response($this->assignmentService->updateAssignment(
            $courseLecturer,
            TeachingAssignmentFormData::fromInput($request->validated(), $courseLecturer),
        ));
    }

    public function destroy(CourseLecturer $courseLecturer): Response
    {
        $this->assignmentService->deleteAssignment($courseLecturer);

        return $this->noContent();
    }
}
