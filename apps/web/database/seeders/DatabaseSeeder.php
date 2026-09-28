<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAccountSeeder;
use Database\Seeders\Demo\MasterDataSeeder;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Database\Seeders\Reference\FacultyStudyProgramSeeder;
use Database\Seeders\Reference\PermissionSeeder;
use Database\Seeders\Reference\RoleSeeder;
use Database\Seeders\Reference\RoomSeeder;
use Database\Seeders\Reference\SuperadminSeeder;
use Database\Seeders\Reference\TimeSlotSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data always; demo data outside production. Every seeder is safe to re-run.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            FacultyStudyProgramSeeder::class,
            TimeSlotSeeder::class,
            RoomSeeder::class,
            ConstraintTypeSeeder::class,
            SuperadminSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([
                MasterDataSeeder::class,
                DemoAccountSeeder::class,
            ]);
        }
    }
}
