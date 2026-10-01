<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum LecturerPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:lecturer:view';
    case CREATE = 'master:lecturer:create';
    case UPDATE = 'master:lecturer:update';
    case DELETE = 'master:lecturer:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Lecturer');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Lecturer'),
            self::CREATE => __('Create Lecturer'),
            self::UPDATE => __('Update Lecturer'),
            self::DELETE => __('Delete Lecturer'),
        };
    }
}
