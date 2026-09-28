<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum StudyProgramPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:study-program:view';
    case CREATE = 'master:study-program:create';
    case UPDATE = 'master:study-program:update';
    case DELETE = 'master:study-program:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Study Program');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Study Program'),
            self::CREATE => __('Create Study Program'),
            self::UPDATE => __('Update Study Program'),
            self::DELETE => __('Delete Study Program'),
        };
    }
}
