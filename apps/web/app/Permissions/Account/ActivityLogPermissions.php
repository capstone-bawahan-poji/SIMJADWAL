<?php

namespace App\Permissions\Account;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum ActivityLogPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'account:activity-log:view';

    public static function getGroupName(): string
    {
        return __('Account').' - '.__('Activity Log');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View Activity Log'),
        };
    }
}
