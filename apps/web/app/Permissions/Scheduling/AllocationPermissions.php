<?php

namespace App\Permissions\Scheduling;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

/**
 * Cells (period, slot, room) a faculty hands to its study programs, and the requests for more.
 */
enum AllocationPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'allocation:view';
    case MANAGE = 'allocation:manage';
    case REQUEST = 'slot-request:create';
    case REVIEW_REQUEST = 'slot-request:review';

    public static function getGroupName(): string
    {
        return __('Room Slot Allocation');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Room Slot Allocation'),
            self::MANAGE => __('Manage Room Slot Allocation'),
            self::REQUEST => __('Request Room Slots'),
            self::REVIEW_REQUEST => __('Review Room Slot Requests'),
        };
    }
}
