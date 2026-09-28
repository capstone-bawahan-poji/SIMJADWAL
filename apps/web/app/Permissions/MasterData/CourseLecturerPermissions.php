<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum CourseLecturerPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:course-lecturer:view';
    case CREATE = 'master:course-lecturer:create';
    case UPDATE = 'master:course-lecturer:update';
    case DELETE = 'master:course-lecturer:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Teaching Assignment');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Teaching Assignment'),
            self::CREATE => __('Create Teaching Assignment'),
            self::UPDATE => __('Update Teaching Assignment'),
            self::DELETE => __('Delete Teaching Assignment'),
        };
    }
}
