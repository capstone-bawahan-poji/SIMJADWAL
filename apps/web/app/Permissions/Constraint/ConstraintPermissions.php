<?php

namespace App\Permissions\Constraint;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum ConstraintPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'constraint:view';
    case UPDATE_WEIGHT = 'constraint:weight:update';
    case UPDATE_PREFERENCE = 'constraint:preference:update';
    case SUBMIT = 'constraint:submit';
    case REVIEW = 'constraint:review';

    public static function getGroupName(): string
    {
        return __('Constraint');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Constraint'),
            self::UPDATE_WEIGHT => __('Update Constraint Weight'),
            self::UPDATE_PREFERENCE => __('Update Lecturer Preference'),
            self::SUBMIT => __('Submit Constraint'),
            self::REVIEW => __('Review Constraint Submission'),
        };
    }
}
