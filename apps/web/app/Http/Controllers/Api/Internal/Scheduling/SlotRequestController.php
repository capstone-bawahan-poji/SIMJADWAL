<?php

namespace App\Http\Controllers\Api\Internal\Scheduling;

use App\Enums\Scheduling\SlotRequestStatus;
use App\Exceptions\Shared\ApiException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\Scheduling\FulfillSlotRequestRequest;
use App\Http\Requests\Scheduling\ListSlotRequestRequest;
use App\Http\Requests\Scheduling\NoteRequest;
use App\Http\Requests\Scheduling\StoreSlotRequestRequest;
use App\Models\Scheduling\SlotRequest;
use App\Services\Scheduling\SlotRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * POS steps 4-6: a program asks its faculty for more room slots.
 */
class SlotRequestController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.SlotRequest::class, only: ['index']),
            new Middleware('can:review,slotRequest', only: ['fulfill', 'reject']),
            new Middleware('can:cancel,slotRequest', only: ['destroy']),
        ];
    }

    public function __construct(protected readonly SlotRequestService $requests) {}

    public function index(ListSlotRequestRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->requests->list(
            $request->user(),
            $v['period'] ?? null,
            isset($v['status']) ? SlotRequestStatus::from($v['status']) : null,
            isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function store(StoreSlotRequestRequest $request): JsonResponse
    {
        $studyProgram = $request->studyProgram()
            ?? throw ApiException::validation(['study_program_id' => [__('validation.required', ['attribute' => 'study_program_id'])]]);
        $this->authorize('requestRoomSlots', $studyProgram);

        return $this->response($this->requests->create(
            $request->user(),
            $studyProgram,
            $request->validated('period'),
            $request->needs(),
            $request->validated('note'),
        ), 201);
    }

    public function fulfill(FulfillSlotRequestRequest $request, SlotRequest $slotRequest): JsonResponse
    {
        return $this->response($this->requests->fulfill($request->user(), $slotRequest, $request->cells(), $request->validated('response_note')));
    }

    public function reject(NoteRequest $request, SlotRequest $slotRequest): JsonResponse
    {
        return $this->response($this->requests->reject($request->user(), $slotRequest, $request->validated('note')));
    }

    public function destroy(SlotRequest $slotRequest): Response
    {
        $this->requests->cancel(request()->user(), $slotRequest);

        return $this->noContent();
    }
}
