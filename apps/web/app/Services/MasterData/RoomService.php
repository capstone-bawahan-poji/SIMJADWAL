<?php

namespace App\Services\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Data\MasterData\RoomData;
use App\Data\MasterData\RoomFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Faculty-scoped readers see their faculty's rooms plus the shared ones.
 */
readonly class RoomService
{
    public function __construct(
        private Room $room,
        private ResolvesOrgScope $orgScope,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * @return LengthAwarePaginator<int, RoomData>
     */
    public function getRooms(User $actor, ?string $search, ?int $facultyId, ?bool $shared, ?bool $inUse, int $perPage): LengthAwarePaginator
    {
        return RoomData::prepareQuery($this->room->newQuery())
            ->unless($this->orgScope->readsAllFaculties($actor), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('faculty_id')
                ->orWhere(fn (Builder $query) => $this->orgScope->applyFacultyScope($query, $actor))))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")
                ->orWhereLike('building', "%{$search}%")))
            ->when($facultyId, fn (Builder $query) => $query->where('faculty_id', $facultyId))
            ->when($shared !== null, fn (Builder $query) => $shared ? $query->whereNull('faculty_id') : $query->whereNotNull('faculty_id'))
            ->when($inUse !== null, fn (Builder $query) => $inUse ? $query->has('activeScheduleDetails') : $query->doesntHave('activeScheduleDetails'))
            ->orderBy('code')
            ->paginate($perPage)
            ->through(fn (Room $room) => RoomData::from($room));
    }

    public function getRoom(Room $room): RoomData
    {
        return RoomData::from(RoomData::loadRelations($room));
    }

    public function createRoom(RoomFormData $data): RoomData
    {
        $room = $this->room->newQuery()->create($this->attributes($data));

        return $this->getRoom($room);
    }

    public function updateRoom(Room $room, RoomFormData $data): RoomData
    {
        if ($data->facultyId !== null && $room->courseLecturers()->exists()) {
            throw ApiException::validation(['faculty_id' => [__('The room hosts TPB classes, so it must stay shared.')]]);
        }

        $room->update($this->attributes($data));

        return $this->getRoom($room->fresh());
    }

    /**
     * Soft delete.
     */
    public function deleteRoom(Room $room): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::TEACHING_ASSIGNMENTS->value => $room->courseLecturers()->count(),
            ReferenceType::SCHEDULES->value => $this->referenceGuard->activeScheduleCount($room->scheduleDetails()),
        ]);

        $room->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(RoomFormData $data): array
    {
        return [
            'faculty_id' => $data->facultyId,
            'code' => $data->code,
            'name' => $data->name,
            'building' => $data->building,
            'floor' => $data->floor,
            'capacity' => $data->capacity,
        ];
    }
}
