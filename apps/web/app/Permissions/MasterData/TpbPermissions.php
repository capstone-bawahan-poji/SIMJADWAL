<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

/**
 * TPB/MKWU data: TPB courses, their groups and their classes.
 */
enum TpbPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:tpb:view';
    case CREATE = 'master:tpb:create';
    case UPDATE = 'master:tpb:update';
    case DELETE = 'master:tpb:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('TPB');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View TPB'),
            self::CREATE => __('Create TPB'),
            self::UPDATE => __('Update TPB'),
            self::DELETE => __('Delete TPB'),
        };
    }
}
