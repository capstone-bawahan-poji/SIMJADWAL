<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\User;
use App\Permissions\Scheduling\GaPresetPermissions;

/**
 * A faculty admin manages the presets of their own faculty. superadmin passes through Gate::before.
 */
class GaParameterPresetPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function viewAny(User $user): bool
    {
        return $user->can(GaPresetPermissions::VIEW);
    }

    /**
     * Listing the presets of one faculty: Gate::authorize('viewFaculty', [GaParameterPreset::class, $facultyId]).
     */
    public function viewFaculty(User $user, ?int $facultyId = null): bool
    {
        return $user->can(GaPresetPermissions::VIEW)
            && $facultyId !== null
            && $this->orgScope->canAccessFaculty($user, $facultyId);
    }

    public function view(User $user, GaParameterPreset $preset): bool
    {
        return $user->can(GaPresetPermissions::VIEW) && $this->orgScope->canAccessFaculty($user, $preset->faculty_id);
    }

    /**
     * Call with the target faculty: Gate::authorize('create', [GaParameterPreset::class, $facultyId]).
     */
    public function create(User $user, ?int $facultyId = null): bool
    {
        return $user->can(GaPresetPermissions::CREATE)
            && $facultyId !== null
            && $this->orgScope->canAccessFaculty($user, $facultyId);
    }

    public function update(User $user, GaParameterPreset $preset): bool
    {
        return $user->can(GaPresetPermissions::UPDATE) && $this->orgScope->canAccessFaculty($user, $preset->faculty_id);
    }

    public function delete(User $user, GaParameterPreset $preset): bool
    {
        return $user->can(GaPresetPermissions::DELETE) && $this->orgScope->canAccessFaculty($user, $preset->faculty_id);
    }
}
