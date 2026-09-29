<?php

namespace App\Policies\MasterData;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\Room;
use App\Models\User;
use App\Permissions\MasterData\RoomPermissions;

/**
 * Faculty admins manage their own faculty's rooms. Shared rooms (faculty_id NULL)
 * are visible to every faculty but managed only by superadmin (Gate::before).
 */
class RoomPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function viewAny(User $user): bool
    {
        return $user->can(RoomPermissions::VIEW);
    }

    public function view(User $user, Room $room): bool
    {
        return $user->can(RoomPermissions::VIEW)
            && ($room->isShared() || $this->orgScope->canAccessFaculty($user, $room->faculty_id));
    }

    /**
     * Call with the target faculty: Gate::authorize('create', [Room::class, $facultyId]).
     */
    public function create(User $user, ?int $facultyId = null): bool
    {
        return $user->can(RoomPermissions::CREATE)
            && $facultyId !== null
            && $this->orgScope->canAccessFaculty($user, $facultyId);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can(RoomPermissions::UPDATE)
            && ! $room->isShared()
            && $this->orgScope->canAccessFaculty($user, $room->faculty_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->can(RoomPermissions::DELETE)
            && ! $room->isShared()
            && $this->orgScope->canAccessFaculty($user, $room->faculty_id);
    }
}
