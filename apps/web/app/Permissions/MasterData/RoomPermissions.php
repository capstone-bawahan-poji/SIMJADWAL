<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum RoomPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:room:view';
    case CREATE = 'master:room:create';
    case UPDATE = 'master:room:update';
    case DELETE = 'master:room:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Room');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Room'),
            self::CREATE => __('Create Room'),
            self::UPDATE => __('Update Room'),
            self::DELETE => __('Delete Room'),
        };
    }
}
