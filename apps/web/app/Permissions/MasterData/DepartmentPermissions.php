<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum DepartmentPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:department:view';
    case CREATE = 'master:department:create';
    case UPDATE = 'master:department:update';
    case DELETE = 'master:department:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Department');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Department'),
            self::CREATE => __('Create Department'),
            self::UPDATE => __('Update Department'),
            self::DELETE => __('Delete Department'),
        };
    }
}
