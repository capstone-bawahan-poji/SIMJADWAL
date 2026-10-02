<?php

namespace Tests\Feature\MasterData;

use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class LecturerApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private StudyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->program = StudyProgram::factory()->create();
    }

    public function test_program_admin_creates_lecturer_in_own_program(): void
    {
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->postJson('/api/v1/lecturers', ['study_program_id' => $this->program->id, 'nip' => '198501012010011001', 'name' => 'Dosen Baru', 'title' => 'S.Kom.'])
            ->assertCreated()
            ->assertJsonPath('data.nip', '198501012010011001')
            ->assertJsonPath('data.study_program.code', $this->program->code)
            ->assertJsonPath('data.can_update', true);

        $this->postJson('/api/v1/lecturers', ['study_program_id' => StudyProgram::factory()->create()->id, 'nip' => '198501012010011002', 'name' => 'Dosen Lain'])
            ->assertForbidden();
    }

    public function test_validation_messages_are_indonesian(): void
    {
        Lecturer::factory()->create(['nip' => '198501012010011003']);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->postJson('/api/v1/lecturers', ['study_program_id' => $this->program->id, 'nip' => '198501012010011003'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.nip.0', 'NIP sudah digunakan.')
            ->assertJsonPath('errors.name.0', 'Nama wajib diisi.');
    }

    public function test_every_admin_lists_lecturers_across_faculties(): void
    {
        Lecturer::factory()->count(2)->create(['study_program_id' => $this->program->id]);
        Lecturer::factory()->create();
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getJson('/api/v1/lecturers')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/lecturers?study_program_id='.$this->program->id)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/lecturers?faculty_id='.$this->program->faculty_id)->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_submitted_program_is_locked_for_program_admin_but_not_faculty_admin(): void
    {
        $program = StudyProgram::factory()->submitted()->create();
        $lecturer = Lecturer::factory()->create(['study_program_id' => $program->id]);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($program)->create());
        $this->patchJson("/api/v1/lecturers/{$lecturer->id}", ['name' => 'Ubah'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONSTRAINTS_LOCKED');

        Sanctum::actingAs(User::factory()->facultyAdmin($program->faculty)->create());
        $this->patchJson("/api/v1/lecturers/{$lecturer->id}", ['name' => 'Ubah'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ubah');
    }

    public function test_lecturer_with_relations_cannot_move_program(): void
    {
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $sibling = StudyProgram::factory()->create(['faculty_id' => $this->program->faculty_id]);
        Sanctum::actingAs(User::factory()->facultyAdmin($this->program->faculty)->create());

        $this->patchJson("/api/v1/lecturers/{$lecturer->id}", ['study_program_id' => $sibling->id])->assertOk();

        CourseLecturer::factory()->create(['lecturer_id' => $lecturer->id]);
        $this->patchJson("/api/v1/lecturers/{$lecturer->id}", ['study_program_id' => $this->program->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('study_program_id');
    }

    public function test_delete_is_soft_and_refused_while_used(): void
    {
        $this->seed(ConstraintTypeSeeder::class);
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $preference = LecturerPreference::query()->create([
            'lecturer_id' => $lecturer->id,
            'constraint_type_id' => ConstraintType::query()->where('code', 'SC_INGIN')->value('id'),
            'time_slot_id' => TimeSlot::factory()->create()->id,
        ]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->deleteJson("/api/v1/lecturers/{$lecturer->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'RESOURCE_IN_USE')
            ->assertJsonPath('errors.references.lecturer_preferences', 1)
            ->assertJsonPath('message', 'Data masih dipakai oleh Preferensi Dosen (1).');

        $preference->delete();
        $this->deleteJson("/api/v1/lecturers/{$lecturer->id}")->assertNoContent();
        $this->assertSoftDeleted($lecturer);

        // The NIP of a deleted lecturer can be used again.
        $this->postJson('/api/v1/lecturers', ['study_program_id' => $this->program->id, 'nip' => $lecturer->nip, 'name' => 'Pengganti'])
            ->assertCreated();
    }

    public function test_internal_endpoint_uses_the_web_session(): void
    {
        $this->actingAs(User::factory()->facultyAdmin(Faculty::factory()->create())->create());

        $this->getJson('/api/internal/lecturers')->assertOk()->assertJsonStructure(['data', 'meta' => ['pagination']]);
    }
}
