<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum FacultyPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:faculty:view';
    case CREATE = 'master:faculty:create';
    case UPDATE = 'master:faculty:update';
    case DELETE = 'master:faculty:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Faculty');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Faculty'),
            self::CREATE => __('Create Faculty'),
            self::UPDATE => __('Update Faculty'),
            self::DELETE => __('Delete Faculty'),
        };
    }
}
