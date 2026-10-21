<?php

namespace App\Permissions\Scheduling;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum GaPresetPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'ga-preset:view';
    case CREATE = 'ga-preset:create';
    case UPDATE = 'ga-preset:update';
    case DELETE = 'ga-preset:delete';

    public static function getGroupName(): string
    {
        return __('GA Preset');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View GA Preset'),
            self::CREATE => __('Create GA Preset'),
            self::UPDATE => __('Update GA Preset'),
            self::DELETE => __('Delete GA Preset'),
        };
    }
}
