<?php

namespace Tests\Feature\MasterData;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class FacultyAndStudyProgramApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_lists_are_not_paginated_and_readable_by_students(): void
    {
        StudyProgram::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->student()->create());

        $this->getJson('/api/v1/faculties')->assertOk()->assertJsonCount(4, 'data')->assertJsonMissingPath('meta');
        $this->getJson('/api/v1/study-programs')->assertOk()->assertJsonCount(4, 'data');
        $this->postJson('/api/v1/faculties', ['code' => 'FX', 'name' => 'Fakultas X'])->assertForbidden();
    }

    public function test_superadmin_manages_faculties_with_unique_code(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $id = $this->postJson('/api/v1/faculties', ['code' => 'FSTI', 'name' => 'Fakultas Sains dan Teknologi Informasi'])
            ->assertCreated()
            ->assertJsonPath('data.study_programs_count', 0)
            ->json('data.id');

        $this->postJson('/api/v1/faculties', ['code' => 'FSTI', 'name' => 'Lain'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->patchJson("/api/v1/faculties/{$id}", ['name' => 'FSTI Baru'])->assertOk()->assertJsonPath('data.code', 'FSTI');
    }

    public function test_faculty_with_programs_cannot_be_deleted(): void
    {
        $program = StudyProgram::factory()->create();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->deleteJson("/api/v1/faculties/{$program->faculty_id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.study_programs', 1);

        $this->deleteJson("/api/v1/study-programs/{$program->id}")->assertNoContent();
        $this->deleteJson("/api/v1/faculties/{$program->faculty_id}")->assertNoContent();
        $this->assertSoftDeleted('faculties', ['id' => $program->faculty_id]);
    }

    public function test_program_with_lecturers_cannot_move_faculty_or_be_deleted(): void
    {
        $program = StudyProgram::factory()->create();
        Lecturer::factory()->create(['study_program_id' => $program->id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->patchJson("/api/v1/study-programs/{$program->id}", ['faculty_id' => Faculty::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('faculty_id');

        $this->patchJson("/api/v1/study-programs/{$program->id}", ['code' => 'IF'])
            ->assertOk()
            ->assertJsonPath('data.code', 'IF')
            ->assertJsonPath('data.constraint_status', 'draft');

        $this->deleteJson("/api/v1/study-programs/{$program->id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.lecturers', 1);
    }

    public function test_faculty_admin_cannot_manage_programs(): void
    {
        $faculty = Faculty::factory()->create();
        Sanctum::actingAs(User::factory()->facultyAdmin($faculty)->create());

        $this->postJson('/api/v1/study-programs', ['faculty_id' => $faculty->id, 'code' => 'IF', 'name' => 'Informatika'])->assertForbidden();
    }
}
