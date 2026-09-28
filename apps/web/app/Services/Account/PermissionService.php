<?php

namespace App\Services\Account;

use App\Extensions\Permissions\Permissionable;
use App\Permissions\Account\UserPermissions;
use App\Permissions\MasterData\CourseLecturerPermissions;
use App\Permissions\MasterData\CoursePermissions;
use App\Permissions\MasterData\FacultyPermissions;
use App\Permissions\MasterData\LecturerPermissions;
use App\Permissions\MasterData\RoomPermissions;
use App\Permissions\MasterData\StudyProgramPermissions;
use App\Permissions\MasterData\TimeSlotPermissions;

readonly class PermissionService
{
    /**
     * Every permission enum in the app. Add a new enum here, then run PermissionSeeder.
     *
     * @return list<class-string<Permissionable&\BackedEnum>>
     */
    public function getAllPermissionEnums(): array
    {
        return [
            UserPermissions::class,
            FacultyPermissions::class,
            StudyProgramPermissions::class,
            TimeSlotPermissions::class,
            RoomPermissions::class,
            LecturerPermissions::class,
            CoursePermissions::class,
            CourseLecturerPermissions::class,
        ];
    }

    /**
     * @return list<string>
     */
    public function getAllPermissionNames(): array
    {
        return array_merge(...array_map(fn (string $enum) => $enum::values(), $this->getAllPermissionEnums()));
    }
}
