<?php

namespace Database\Seeders\Reference;

use App\Enums\Account\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.superadmin.email');
        $password = config('seeding.superadmin.password');

        if (blank($password)) {
            throw new RuntimeException('SUPERADMIN_PASSWORD is empty. Set it in .env before seeding.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => $password],
        );

        $user->syncRoles([Role::SUPER_ADMIN->value]);
    }
}
