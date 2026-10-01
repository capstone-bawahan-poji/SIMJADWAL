<?php

namespace App\Extensions\Permissions;

/**
 * Contract for permission enums, so role management can list them grouped and labelled.
 */
interface Permissionable
{
    public static function getGroupName(): string;

    public function getLabel(): string;
}
