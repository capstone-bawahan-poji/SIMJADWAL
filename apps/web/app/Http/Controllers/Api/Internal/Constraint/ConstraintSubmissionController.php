<?php

namespace App\Http\Controllers\Api\Internal\Constraint;

use App\Enums\Constraint\ConstraintStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\Constraint\ListConstraintSubmissionRequest;
use App\Http\Requests\Constraint\ReturnConstraintSubmissionRequest;
use App\Models\MasterData\StudyProgram;
use App\Services\Constraint\ConstraintSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * /constraint-submissions: constraint progress per study program, keyed by the program.
 */
class ConstraintSubmissionController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAnyConstraints,'.StudyProgram::class, only: ['index']),
            new Middleware('can:viewConstraints,studyProgram', only: ['show']),
            new Middleware('can:submitConstraints,studyProgram', only: ['submit']),
            new Middleware('can:reviewConstraints,studyProgram', only: ['accept', 'return']),
        ];
    }

    public function __construct(
        private readonly Request $request,
        private readonly ConstraintSubmissionService $submissionService,
    ) {}

    public function index(ListConstraintSubmissionRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->submissionService->getSubmissions(
            actor: $this->request->user(),
            search: $v['q'] ?? null,
            status: isset($v['status']) ? ConstraintStatus::from($v['status']) : null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->submissionService->getSubmission($studyProgram));
    }

    public function submit(StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->submissionService->submit($studyProgram));
    }

    public function accept(StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->submissionService->accept($studyProgram, $this->request->user()));
    }

    public function return(ReturnConstraintSubmissionRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        return $this->response($this->submissionService->return($studyProgram, $this->request->user(), $request->validated('note')));
    }
}
