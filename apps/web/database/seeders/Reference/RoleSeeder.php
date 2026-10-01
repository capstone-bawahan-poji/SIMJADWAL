<?php

namespace Database\Seeders\Reference;

use App\Enums\Account\Role as RoleEnum;
use App\Permissions\MasterData\CourseLecturerPermissions;
use App\Permissions\MasterData\CoursePermissions;
use App\Permissions\MasterData\FacultyPermissions;
use App\Permissions\MasterData\LecturerPermissions;
use App\Permissions\MasterData\RoomPermissions;
use App\Permissions\MasterData\StudyProgramPermissions;
use App\Permissions\MasterData\TimeSlotPermissions;
use App\Services\Account\PermissionService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role -> permission matrix, following x-roles in docs/api/openapi.yaml.
 * superadmin also passes every check through Gate::before.
 */
class RoleSeeder extends Seeder
{
    public function run(PermissionService $permissionService, PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        $readReference = [
            FacultyPermissions::VIEW,
            StudyProgramPermissions::VIEW,
            TimeSlotPermissions::VIEW,
        ];

        $matrix = [
            RoleEnum::SUPER_ADMIN->value => $permissionService->getAllPermissionNames(),
            RoleEnum::FACULTY_ADMIN->value => [
                ...$readReference,
                ...RoomPermissions::cases(),
                LecturerPermissions::VIEW,
                CoursePermissions::VIEW,
                CourseLecturerPermissions::VIEW,
            ],
            RoleEnum::STUDY_PROGRAM_ADMIN->value => [
                ...$readReference,
                RoomPermissions::VIEW,
                ...LecturerPermissions::cases(),
                ...CoursePermissions::cases(),
                ...CourseLecturerPermissions::cases(),
            ],
            RoleEnum::LECTURER->value => $readReference,
            RoleEnum::STUDENT->value => $readReference,
        ];

        foreach ($matrix as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions(
                array_map(fn ($permission) => $permission instanceof \BackedEnum ? $permission->value : $permission, $permissions),
            );
        }
    }
}
