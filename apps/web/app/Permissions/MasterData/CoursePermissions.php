<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum CoursePermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:course:view';
    case CREATE = 'master:course:create';
    case UPDATE = 'master:course:update';
    case DELETE = 'master:course:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Course');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Course'),
            self::CREATE => __('Create Course'),
            self::UPDATE => __('Update Course'),
            self::DELETE => __('Delete Course'),
        };
    }
}
