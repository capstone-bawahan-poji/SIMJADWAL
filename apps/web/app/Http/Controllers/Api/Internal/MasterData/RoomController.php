<?php

namespace App\Http\Controllers\Api\Internal\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\MasterData\RoomFormData;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\MasterData\Room\ListRoomRequest;
use App\Http\Requests\MasterData\Room\StoreRoomRequest;
use App\Http\Requests\MasterData\Room\UpdateRoomRequest;
use App\Models\MasterData\Room;
use App\Services\MasterData\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoomController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Room::class, only: ['index']),
            new Middleware('can:view,room', only: ['show']),
            new Middleware('can:update,room', only: ['update']),
            new Middleware('can:delete,room', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly Request $request,
        private readonly RoomService $roomService,
        private readonly ResolvesOrgScope $orgScope,
    ) {}

    public function index(ListRoomRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->roomService->getRooms(
            actor: $this->request->user(),
            search: $v['q'] ?? null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            shared: isset($v['shared']) ? (bool) $v['shared'] : null,
            inUse: isset($v['in_use']) ? (bool) $v['in_use'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(Room $room): JsonResponse
    {
        return $this->response($this->roomService->getRoom($room));
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $v = $request->validated();
        $facultyId = array_key_exists('faculty_id', $v)
            ? ($v['faculty_id'] === null ? null : (int) $v['faculty_id'])
            : $this->orgScope->facultyId($this->request->user());
        $this->authorize('create', [Room::class, $facultyId]);

        return $this->response($this->roomService->createRoom(RoomFormData::fromInput($v, $facultyId)), 201);
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $v = $request->validated();
        $facultyId = $room->faculty_id;

        if (array_key_exists('faculty_id', $v)) {
            $facultyId = $v['faculty_id'] === null ? null : (int) $v['faculty_id'];

            if ($facultyId !== $room->faculty_id) {
                $this->authorize('create', [Room::class, $facultyId]);
            }
        }

        return $this->response($this->roomService->updateRoom($room, RoomFormData::fromInput($v, $facultyId, $room)));
    }

    public function destroy(Room $room): Response
    {
        $this->roomService->deleteRoom($room);

        return $this->noContent();
    }
}
