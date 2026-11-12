<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAccountSeeder;
use Database\Seeders\Demo\MasterDataSeeder;
use Database\Seeders\Demo\SchedulingRunSeeder;
use Database\Seeders\Demo\TpbSeeder;
use Database\Seeders\Reference\AcademicPeriodSeeder;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Database\Seeders\Reference\FacultyStudyProgramSeeder;
use Database\Seeders\Reference\GaPresetSeeder;
use Database\Seeders\Reference\PermissionSeeder;
use Database\Seeders\Reference\RoleSeeder;
use Database\Seeders\Reference\RoomSeeder;
use Database\Seeders\Reference\SuperadminSeeder;
use Database\Seeders\Reference\TimeSlotSeeder;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data always. Demo data when SEED_DEMO is true, or when it is unset outside production.
     * Every seeder is safe to re-run.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            FacultyStudyProgramSeeder::class,
            TimeSlotSeeder::class,
            AcademicPeriodSeeder::class,
            RoomSeeder::class,
            ConstraintTypeSeeder::class,
            GaPresetSeeder::class,
            SuperadminSeeder::class,
        ]);

        if (config('seeding.demo') ?? ! app()->isProduction()) {
            if (app()->isProduction() && config('seeding.default_password') === 'password') {
                throw new RuntimeException('Set SEED_DEFAULT_PASSWORD to something other than "password" before seeding demo accounts in production.');
            }

            $this->call([
                MasterDataSeeder::class,
                TpbSeeder::class,
                DemoAccountSeeder::class,
                SchedulingRunSeeder::class,
            ]);
        }
    }
}
