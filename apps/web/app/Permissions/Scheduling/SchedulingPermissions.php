<?php

namespace App\Permissions\Scheduling;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum SchedulingPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW_DATASET = 'scheduling:dataset:view';
    case VIEW_RUN = 'scheduling:run:view';
    case UPLOAD_RUN = 'scheduling:run:upload';

    public static function getGroupName(): string
    {
        return __('Scheduling');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW_DATASET => __('View Scheduling Dataset'),
            self::VIEW_RUN => __('View Scheduling Run'),
            self::UPLOAD_RUN => __('Upload Scheduling Run'),
        };
    }
}
