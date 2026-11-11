<?php

namespace Database\Seeders\Reference;

use App\Enums\Account\Role as RoleEnum;
use App\Permissions\Constraint\ConstraintPermissions;
use App\Permissions\MasterData\AcademicPeriodPermissions;
use App\Permissions\MasterData\CourseLecturerPermissions;
use App\Permissions\MasterData\CoursePermissions;
use App\Permissions\MasterData\DepartmentPermissions;
use App\Permissions\MasterData\FacultyPermissions;
use App\Permissions\MasterData\LecturerPermissions;
use App\Permissions\MasterData\RoomPermissions;
use App\Permissions\MasterData\StudyProgramPermissions;
use App\Permissions\MasterData\TimeSlotPermissions;
use App\Permissions\MasterData\TpbPermissions;
use App\Permissions\Scheduling\AllocationPermissions;
use App\Permissions\Scheduling\GaPresetPermissions;
use App\Permissions\Scheduling\SchedulingPermissions;
use App\Services\Account\PermissionService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role -> permission matrix. Master data follows docs/plan/2026-09-30-modul-2-backend.md §1,
 * the rest follows x-roles in docs/api/openapi.yaml.
 * superadmin also passes every check through Gate::before.
 */
class RoleSeeder extends Seeder
{
    public function run(PermissionService $permissionService, PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        $readReference = [
            FacultyPermissions::VIEW,
            DepartmentPermissions::VIEW,
            StudyProgramPermissions::VIEW,
            TimeSlotPermissions::VIEW,
            AcademicPeriodPermissions::VIEW,
        ];

        $matrix = [
            RoleEnum::SUPER_ADMIN->value => $permissionService->getAllPermissionNames(),
            // Faculty admins may step in on every program of their faculty, even after submit.
            RoleEnum::FACULTY_ADMIN->value => [
                ...$readReference,
                ...RoomPermissions::cases(),
                ...LecturerPermissions::cases(),
                ...CoursePermissions::cases(),
                ...CourseLecturerPermissions::cases(),
                TpbPermissions::VIEW,
                ConstraintPermissions::VIEW,
                ConstraintPermissions::UPDATE_WEIGHT,
                ConstraintPermissions::REVIEW,
                // POS 040/IT10.1/OT.07/2026: the faculty hands out cells, reviews and publishes. Programs run the GA.
                SchedulingPermissions::VIEW_RUN,
                SchedulingPermissions::PUBLISH_RUN,
                SchedulingPermissions::VIEW_SCHEDULE,
                SchedulingPermissions::REVIEW_SCHEDULE,
                SchedulingPermissions::IMPORT_TPB,
                GaPresetPermissions::VIEW,
                AllocationPermissions::VIEW,
                AllocationPermissions::MANAGE,
                AllocationPermissions::REVIEW_REQUEST,
            ],
            RoleEnum::TPB_ADMIN->value => [
                ...$readReference,
                RoomPermissions::VIEW,
                LecturerPermissions::VIEW,
                CoursePermissions::VIEW,
                CourseLecturerPermissions::VIEW,
                ...TpbPermissions::cases(),
                SchedulingPermissions::VIEW_DATASET,
                SchedulingPermissions::VIEW_RUN,
                SchedulingPermissions::UPLOAD_RUN,
                SchedulingPermissions::ACTIVATE_RUN,
                SchedulingPermissions::PUBLISH_RUN,
                SchedulingPermissions::VIEW_SCHEDULE,
                SchedulingPermissions::IMPORT_TPB,
                ...GaPresetPermissions::cases(),
                AllocationPermissions::VIEW,
            ],
            RoleEnum::STUDY_PROGRAM_ADMIN->value => [
                ...$readReference,
                RoomPermissions::VIEW,
                ...LecturerPermissions::cases(),
                ...CoursePermissions::cases(),
                ...CourseLecturerPermissions::cases(),
                TpbPermissions::VIEW,
                ConstraintPermissions::VIEW,
                ConstraintPermissions::UPDATE_PREFERENCE,
                ConstraintPermissions::SUBMIT,
                SchedulingPermissions::VIEW_DATASET,
                SchedulingPermissions::VIEW_RUN,
                SchedulingPermissions::UPLOAD_RUN,
                SchedulingPermissions::ACTIVATE_RUN,
                SchedulingPermissions::VIEW_SCHEDULE,
                SchedulingPermissions::OVERRIDE_SCHEDULE,
                SchedulingPermissions::SUBMIT_SCHEDULE,
                ...GaPresetPermissions::cases(),
                AllocationPermissions::VIEW,
                AllocationPermissions::REQUEST,
            ],
            RoleEnum::LECTURER->value => [...$readReference, SchedulingPermissions::VIEW_SCHEDULE],
        ];

        foreach ($matrix as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions(
                array_map(fn ($permission) => $permission instanceof \BackedEnum ? $permission->value : $permission, $permissions),
            );
        }
    }
}
