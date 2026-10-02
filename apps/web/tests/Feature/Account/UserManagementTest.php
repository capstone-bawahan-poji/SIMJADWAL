<?php

namespace Tests\Feature\Account;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_superadmin_lists_users_with_pagination_meta(): void
    {
        User::factory()->count(3)->create();
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 4)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_list_filters_by_role(): void
    {
        User::factory()->studyProgramAdmin()->create();
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/users?role=admin_prodi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'admin_prodi');
    }

    public function test_non_superadmin_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin()->create());

        $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->postJson('/api/v1/users', [])->assertForbidden();
    }

    public function test_create_faculty_admin_requires_faculty_and_prohibits_other_scopes(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $studyProgram = StudyProgram::factory()->create();

        $this->postJson('/api/v1/users', $this->payload(['role' => 'admin_fakultas', 'study_program_id' => $studyProgram->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['faculty_id', 'study_program_id']);

        $this->postJson('/api/v1/users', $this->payload(['role' => 'admin_fakultas', 'faculty_id' => $studyProgram->faculty_id]))
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin_fakultas')
            ->assertJsonPath('data.faculty_id', $studyProgram->faculty_id)
            ->assertJsonPath('data.study_program_id', null);
    }

    public function test_create_tpb_admin_takes_no_scope(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/users', $this->payload(['role' => 'admin_tpb', 'faculty_id' => Faculty::factory()->create()->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('faculty_id');

        $this->postJson('/api/v1/users', $this->payload(['role' => 'admin_tpb']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin_tpb')
            ->assertJsonPath('data.faculty_id', null)
            ->assertJsonPath('data.study_program_id', null);
    }

    public function test_create_lecturer_account_links_free_lecturer_only(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $lecturer = Lecturer::factory()->create();
        $taken = Lecturer::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->postJson('/api/v1/users', $this->payload(['role' => 'dosen', 'lecturer_id' => $taken->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lecturer_id');

        $response = $this->postJson('/api/v1/users', $this->payload(['role' => 'dosen', 'lecturer_id' => $lecturer->id]))
            ->assertCreated()
            ->assertJsonPath('data.lecturer_id', $lecturer->id);

        $this->assertSame($response->json('data.id'), $lecturer->fresh()->user_id);
    }

    public function test_changing_role_clears_old_scope_and_unlinks_lecturer(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $lecturer = Lecturer::factory()->create();
        $user = User::factory()->lecturer($lecturer)->create();
        $faculty = Faculty::factory()->create();

        $this->patchJson("/api/v1/users/{$user->id}", ['role' => 'admin_fakultas', 'faculty_id' => $faculty->id])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin_fakultas')
            ->assertJsonPath('data.faculty_id', $faculty->id)
            ->assertJsonPath('data.lecturer_id', null);

        $this->assertNull($lecturer->fresh()->user_id);
    }

    public function test_partial_update_keeps_role_and_scope(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $user = User::factory()->studyProgramAdmin()->create();

        $this->patchJson("/api/v1/users/{$user->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.role', 'admin_prodi')
            ->assertJsonPath('data.study_program_id', $user->study_program_id);
    }

    public function test_deactivation_revokes_tokens(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $user = User::factory()->create();
        $user->createToken('android');

        $this->patchJson("/api/v1/users/{$user->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_superadmin_cannot_deactivate_self(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->patchJson("/api/v1/users/{$this->superAdmin->id}/status", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');
    }

    public function test_internal_route_uses_web_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->getJson('/api/internal/users')
            ->assertOk()
            ->assertJsonPath('data.0.can_update', true);
    }

    public function test_account_created_without_password_gets_the_default_password(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $studyProgram = StudyProgram::factory()->create();

        $id = $this->postJson('/api/v1/users', [
            'name' => 'Mahasiswa Baru',
            'email' => 'baru@example.test',
            'role' => 'mahasiswa',
            'study_program_id' => $studyProgram->id,
        ])->assertCreated()->json('data.id');

        $this->assertTrue(Hash::check(config('accounts.default_password'), User::find($id)->password));
    }

    public function test_identity_number_is_unique_and_digits_only(): void
    {
        User::factory()->create(['identity_number' => '11221001']);
        Sanctum::actingAs($this->superAdmin);
        $studyProgram = StudyProgram::factory()->create();

        $this->postJson('/api/v1/users', $this->payload(['role' => 'mahasiswa', 'study_program_id' => $studyProgram->id, 'identity_number' => '11221001']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.identity_number.0', 'Nomor identitas sudah digunakan.');

        $this->postJson('/api/v1/users', $this->payload(['role' => 'mahasiswa', 'study_program_id' => $studyProgram->id, 'identity_number' => 'NIM-1']))
            ->assertJsonValidationErrors('identity_number');

        $this->postJson('/api/v1/users', $this->payload(['role' => 'mahasiswa', 'study_program_id' => $studyProgram->id, 'identity_number' => '11221002']))
            ->assertCreated()
            ->assertJsonPath('data.identity_number', '11221002')
            ->assertJsonPath('data.study_program.code', $studyProgram->code);
    }

    public function test_lecturer_account_shows_the_lecturer_nip(): void
    {
        $lecturer = Lecturer::factory()->create(['nip' => '198501012010011001']);
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/users', $this->payload(['role' => 'dosen', 'lecturer_id' => $lecturer->id, 'identity_number' => '123456789']))
            ->assertJsonValidationErrors('identity_number');

        $this->postJson('/api/v1/users', $this->payload(['role' => 'dosen', 'lecturer_id' => $lecturer->id]))
            ->assertCreated()
            ->assertJsonPath('data.identity_number', '198501012010011001')
            ->assertJsonPath('data.lecturer.study_program_id', $lecturer->study_program_id);
    }

    public function test_list_filters_by_faculty_and_study_program_scope(): void
    {
        $program = StudyProgram::factory()->create();
        $otherProgram = StudyProgram::factory()->create(['faculty_id' => $program->faculty_id]);
        $facultyAdmin = User::factory()->facultyAdmin($program->faculty)->create();
        $programAdmin = User::factory()->studyProgramAdmin($program)->create();
        $student = User::factory()->student($otherProgram)->create();
        $lecturerAccount = User::factory()->lecturer(Lecturer::factory()->create(['study_program_id' => $program->id]))->create();
        User::factory()->student()->create();
        User::factory()->tpbAdmin()->create();
        Sanctum::actingAs($this->superAdmin);

        $byFaculty = $this->getJson("/api/v1/users?faculty_id={$program->faculty_id}")->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$facultyAdmin->id, $programAdmin->id, $student->id, $lecturerAccount->id], $byFaculty);

        $byProgram = $this->getJson("/api/v1/users?study_program_id={$program->id}")->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$programAdmin->id, $lecturerAccount->id], $byProgram);

        $this->getJson("/api/v1/users?faculty_id={$program->faculty_id}&study_program_id={$otherProgram->id}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$student->id]);

        $this->getJson('/api/v1/users?faculty_id='.Faculty::factory()->create()->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_matches_identity_number_and_lecturer_nip(): void
    {
        $student = User::factory()->student()->create(['identity_number' => '11229999']);
        $lecturerAccount = User::factory()->lecturer(Lecturer::factory()->create(['nip' => '197001012000011999']))->create();
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/users?q=11229999')->assertOk()->assertJsonPath('data.*.id', [$student->id]);
        $this->getJson('/api/v1/users?q=197001012000011999')->assertOk()->assertJsonPath('data.*.id', [$lecturerAccount->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides): array
    {
        return [
            'name' => 'New User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
            ...$overrides,
        ];
    }
}
