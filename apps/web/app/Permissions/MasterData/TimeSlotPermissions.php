<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum TimeSlotPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:time-slot:view';
    case CREATE = 'master:time-slot:create';
    case UPDATE = 'master:time-slot:update';
    case DELETE = 'master:time-slot:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Time Slot');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Time Slot'),
            self::CREATE => __('Create Time Slot'),
            self::UPDATE => __('Update Time Slot'),
            self::DELETE => __('Delete Time Slot'),
        };
    }
}
