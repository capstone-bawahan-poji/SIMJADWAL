<?php

namespace App\Policies\MasterData;

use App\Models\MasterData\TimeSlot;
use App\Models\User;
use App\Permissions\MasterData\TimeSlotPermissions;

class TimeSlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(TimeSlotPermissions::VIEW);
    }

    public function view(User $user, TimeSlot $timeSlot): bool
    {
        return $user->can(TimeSlotPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(TimeSlotPermissions::CREATE);
    }

    public function update(User $user, TimeSlot $timeSlot): bool
    {
        return $user->can(TimeSlotPermissions::UPDATE);
    }

    public function delete(User $user, TimeSlot $timeSlot): bool
    {
        return $user->can(TimeSlotPermissions::DELETE);
    }
}
