<?php

namespace Tests\Feature\MasterData;

use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_everyone_reads_departments_but_only_superadmin_writes(): void
    {
        $department = Department::factory()->create();
        Department::factory()->create();
        Sanctum::actingAs(User::factory()->facultyAdmin($department->faculty)->create());

        $this->getJson('/api/v1/departments')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('meta');
        $this->getJson("/api/v1/departments?faculty_id={$department->faculty_id}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$department->id])
            ->assertJsonPath('data.0.can_update', false);
        $this->postJson('/api/v1/departments', ['faculty_id' => $department->faculty_id, 'code' => 'JX', 'name' => 'Jurusan X'])->assertForbidden();
    }

    public function test_superadmin_manages_departments_with_unique_code(): void
    {
        $faculty = Faculty::factory()->create();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $id = $this->postJson('/api/v1/departments', ['faculty_id' => $faculty->id, 'code' => 'JTEIB', 'name' => 'Jurusan Teknik Elektro, Informatika, dan Bisnis'])
            ->assertCreated()
            ->assertJsonPath('data.faculty.code', $faculty->code)
            ->assertJsonPath('data.study_programs_count', 0)
            ->json('data.id');

        $this->postJson('/api/v1/departments', ['faculty_id' => $faculty->id, 'code' => 'JTEIB', 'name' => 'Lain'])
            ->assertJsonValidationErrors('code');

        $this->patchJson("/api/v1/departments/{$id}", ['name' => 'Jurusan Baru'])->assertOk()->assertJsonPath('data.code', 'JTEIB');
        $this->getJson("/api/v1/faculties/{$faculty->id}")->assertJsonPath('data.departments_count', 1);
    }

    public function test_department_with_programs_cannot_move_or_be_deleted(): void
    {
        $department = Department::factory()->create();
        StudyProgram::factory()->create(['faculty_id' => $department->faculty_id, 'department_id' => $department->id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->patchJson("/api/v1/departments/{$department->id}", ['faculty_id' => Faculty::factory()->create()->id])
            ->assertJsonValidationErrors('faculty_id');

        $this->deleteJson("/api/v1/departments/{$department->id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.study_programs', 1);

        $this->deleteJson("/api/v1/faculties/{$department->faculty_id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.departments', 1);
    }

    public function test_study_program_department_must_be_in_its_faculty(): void
    {
        $department = Department::factory()->create();
        $program = StudyProgram::factory()->create(['faculty_id' => $department->faculty_id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->patchJson("/api/v1/study-programs/{$program->id}", ['department_id' => Department::factory()->create()->id])
            ->assertJsonValidationErrors('department_id');

        $this->patchJson("/api/v1/study-programs/{$program->id}", ['department_id' => $department->id])
            ->assertOk()
            ->assertJsonPath('data.department.code', $department->code);

        $this->getJson("/api/v1/study-programs?department_id={$department->id}")->assertOk()->assertJsonPath('data.*.id', [$program->id]);

        $this->patchJson("/api/v1/study-programs/{$program->id}", ['department_id' => null])
            ->assertOk()
            ->assertJsonPath('data.department', null);
    }

    public function test_study_program_shows_its_coordinator(): void
    {
        $program = StudyProgram::factory()->create();
        $coordinator = User::factory()->studyProgramAdmin($program)->create(['identity_number' => '198301012010011001']);
        User::factory()->student($program)->create();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/v1/study-programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('data.coordinator.id', $coordinator->id)
            ->assertJsonPath('data.coordinator.identity_number', '198301012010011001');

        $this->getJson('/api/v1/study-programs')->assertOk()->assertJsonPath('data.0.coordinator.name', $coordinator->name);
    }
}
