<?php

namespace App\Permissions\Account;

use App\Extensions\Permissions\Permissionable;
use App\Extensions\Permissions\PermissionGroup;

enum UserPermissions: string implements Permissionable
{
    use PermissionGroup;

    case VIEW = 'account:user:view';
    case CREATE = 'account:user:create';
    case UPDATE = 'account:user:update';
    case UPDATE_STATUS = 'account:user:update-status';

    public static function getGroupName(): string
    {
        return __('Account').' - '.__('User');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VIEW => __('View User'),
            self::CREATE => __('Create User'),
            self::UPDATE => __('Update User'),
            self::UPDATE_STATUS => __('Activate or Deactivate User'),
        };
    }
}
