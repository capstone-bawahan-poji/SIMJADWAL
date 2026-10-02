<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Data\MasterData\TpbGroupFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\MasterData\TpbGroup\ListTpbGroupRequest;
use App\Http\Requests\MasterData\TpbGroup\StoreTpbGroupRequest;
use App\Http\Requests\MasterData\TpbGroup\UpdateTpbGroupRequest;
use App\Models\MasterData\TpbGroup;
use App\Services\MasterData\TpbGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TpbGroupController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.TpbGroup::class, only: ['index']),
            new Middleware('can:view,tpbGroup', only: ['show']),
            new Middleware('can:create,'.TpbGroup::class, only: ['store']),
            new Middleware('can:update,tpbGroup', only: ['update']),
            new Middleware('can:delete,tpbGroup', only: ['destroy']),
        ];
    }

    public function __construct(private readonly TpbGroupService $tpbGroupService) {}

    public function index(ListTpbGroupRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->tpbGroupService->getGroups(
            courseId: isset($v['course_id']) ? (int) $v['course_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            timeSlotId: isset($v['time_slot_id']) ? (int) $v['time_slot_id'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(TpbGroup $tpbGroup): JsonResponse
    {
        return $this->response($this->tpbGroupService->getGroup($tpbGroup));
    }

    public function store(StoreTpbGroupRequest $request): JsonResponse
    {
        return $this->response($this->tpbGroupService->createGroup(TpbGroupFormData::fromInput($request->validated())), 201);
    }

    public function update(UpdateTpbGroupRequest $request, TpbGroup $tpbGroup): JsonResponse
    {
        return $this->response($this->tpbGroupService->updateGroup($tpbGroup, TpbGroupFormData::fromInput($request->validated(), $tpbGroup)));
    }

    public function destroy(TpbGroup $tpbGroup): Response
    {
        $this->tpbGroupService->deleteGroup($tpbGroup);

        return $this->noContent();
    }
}
