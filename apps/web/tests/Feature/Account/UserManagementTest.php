<?php

namespace Tests\Feature\Account;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
