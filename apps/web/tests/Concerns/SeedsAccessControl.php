<?php

namespace Tests\Concerns;

use Database\Seeders\Reference\PermissionSeeder;
use Database\Seeders\Reference\RoleSeeder;

/**
 * Seeds spatie permissions and roles, which UserFactory role states need.
 */
trait SeedsAccessControl
{
    protected function seedAccessControl(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }
}
