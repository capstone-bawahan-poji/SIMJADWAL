<?php

namespace App\Policies\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\User;
use App\Permissions\Scheduling\GaPresetPermissions;

/**
 * A study program admin manages the presets of their program, admin_tpb the TPB presets
 * (program null). A faculty admin reads the presets of its programs. superadmin passes through Gate::before.
 */
class GaParameterPresetPolicy
{
    public function __construct(private readonly ResolvesOrgScope $orgScope) {}

    public function viewAny(User $user): bool
    {
        return $user->can(GaPresetPermissions::VIEW);
    }

    /**
     * Listing the presets of one program (null = TPB): Gate::authorize('viewScope', [GaParameterPreset::class, $studyProgram]).
     */
    public function viewScope(User $user, ?StudyProgram $studyProgram = null): bool
    {
        return $user->can(GaPresetPermissions::VIEW) && $this->canAccess($user, $studyProgram);
    }

    public function view(User $user, GaParameterPreset $preset): bool
    {
        return $user->can(GaPresetPermissions::VIEW) && $this->canAccessPreset($user, $preset);
    }

    /**
     * Call with the target program: Gate::authorize('create', [GaParameterPreset::class, $studyProgram]).
     */
    public function create(User $user, ?StudyProgram $studyProgram = null): bool
    {
        return $user->can(GaPresetPermissions::CREATE) && $this->canAccess($user, $studyProgram);
    }

    public function update(User $user, GaParameterPreset $preset): bool
    {
        return $user->can(GaPresetPermissions::UPDATE) && $this->canAccessPreset($user, $preset);
    }

    public function delete(User $user, GaParameterPreset $preset): bool
    {
        return $user->can(GaPresetPermissions::DELETE) && $this->canAccessPreset($user, $preset);
    }

    private function canAccess(User $user, ?StudyProgram $studyProgram): bool
    {
        return $this->orgScope->canAccessScheduleScope($user, $studyProgram?->faculty_id, $studyProgram?->id);
    }

    /**
     * Presets saved for a whole faculty before the GA moved to the programs are no one's but superadmin's.
     */
    private function canAccessPreset(User $user, GaParameterPreset $preset): bool
    {
        if ($preset->faculty_id !== null && $preset->study_program_id === null) {
            return false;
        }

        return $this->orgScope->canAccessScheduleScope($user, $preset->faculty_id, $preset->study_program_id);
    }
}
