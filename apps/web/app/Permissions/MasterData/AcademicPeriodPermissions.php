<?php

namespace App\Permissions\MasterData;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum AcademicPeriodPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'master:academic-period:view';
    case CREATE = 'master:academic-period:create';
    case UPDATE = 'master:academic-period:update';
    case DELETE = 'master:academic-period:delete';

    public static function getGroupName(): string
    {
        return __('Master').' - '.__('Academic Period');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Academic Period'),
            self::CREATE => __('Create Academic Period'),
            self::UPDATE => __('Update Academic Period'),
            self::DELETE => __('Delete Academic Period'),
        };
    }
}
