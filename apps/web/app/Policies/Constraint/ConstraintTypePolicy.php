<?php

namespace App\Policies\Constraint;

use App\Enums\Constraint\ConstraintCategory;
use App\Models\Constraint\ConstraintType;
use App\Models\User;
use App\Permissions\Constraint\ConstraintPermissions;

/**
 * Hard constraints are fixed rules of the engine: nobody changes their weight.
 */
class ConstraintTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(ConstraintPermissions::VIEW);
    }

    public function update(User $user, ConstraintType $constraintType): bool
    {
        return $user->can(ConstraintPermissions::UPDATE_WEIGHT) && $constraintType->category === ConstraintCategory::SOFT;
    }
}
