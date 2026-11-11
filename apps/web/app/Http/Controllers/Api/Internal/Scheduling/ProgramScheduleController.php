<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Scheduling\NoteRequest;
use App\Http\Requests\Scheduling\PeriodRequest;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Services\Scheduling\ProgramScheduleSubmissionService;
use Illuminate\Http\JsonResponse;

class ProgramScheduleController extends ApiController
{
    public function __construct(protected readonly ProgramScheduleSubmissionService $submissions) {}

    public function show(PeriodRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        $this->authorize('viewSchedule', $studyProgram);

        return $this->response($this->submissions->show($studyProgram, $request->period()));
    }

    public function submit(PeriodRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        $this->authorize('submitSchedule', $studyProgram);

        return $this->response($this->submissions->submit($request->user(), $studyProgram, $request->period()));
    }

    public function withdraw(PeriodRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        $this->authorize('submitSchedule', $studyProgram);

        return $this->response($this->submissions->withdraw($request->user(), $studyProgram, $request->period()));
    }

    public function return(NoteRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        $this->authorize('reviewSchedule', $studyProgram);

        return $this->response($this->submissions->return($request->user(), $studyProgram, $request->period(), $request->validated('note')));
    }

    public function faculty(PeriodRequest $request, Faculty $faculty): JsonResponse
    {
        $this->authorize('reviewSchedules', $faculty);

        return $this->response($this->submissions->forFaculty($faculty, $request->period()));
    }
}
