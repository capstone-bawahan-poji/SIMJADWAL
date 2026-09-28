<?php

namespace Database\Seeders\Reference;

use App\Services\Account\PermissionService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(PermissionService $permissionService, PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        foreach ($permissionService->getAllPermissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
