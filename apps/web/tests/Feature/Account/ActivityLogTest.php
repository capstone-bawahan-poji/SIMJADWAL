<?php

namespace Tests\Feature\Account;

use App\Models\Account\ActivityLog;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_account_changes_are_logged_with_the_causer_and_without_passwords(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $studyProgram = StudyProgram::factory()->create();
        $faculty = Faculty::factory()->create();

        $id = $this->postJson('/api/v1/users', [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@example.test',
            'role' => 'admin_prodi',
            'study_program_id' => $studyProgram->id,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/users/{$id}", ['role' => 'admin_fakultas', 'faculty_id' => $faculty->id, 'password' => 'rahasia123'])->assertOk();
        $this->patchJson("/api/v1/users/{$id}", ['name' => 'Admin Baru'])->assertOk();
        $this->patchJson("/api/v1/users/{$id}/status", ['is_active' => false])->assertOk();

        $logs = ActivityLog::query()->where('subject_id', $id)->orderBy('id')->get();
        $this->assertSame(['user.created', 'user.updated', 'user.status_changed'], $logs->pluck('event')->all());
        $this->assertTrue($logs->every(fn (ActivityLog $log) => $log->causer_id === $this->superAdmin->id));

        $update = $logs[1];
        $this->assertSame(['role' => 'admin_prodi', 'faculty_id' => null, 'study_program_id' => $studyProgram->id], $update->attribute_changes['old']);
        $this->assertSame(['role' => 'admin_fakultas', 'faculty_id' => $faculty->id, 'study_program_id' => null], $update->attribute_changes['attributes']);
        $this->assertTrue($update->properties['password_changed']);
        $this->assertStringNotContainsString('rahasia123', $logs->toJson());
    }

    public function test_superadmin_reads_the_log_newest_first_and_others_cannot(): void
    {
        $this->actingAs($this->superAdmin);
        $target = User::factory()->create();
        $this->patchJson("/api/internal/users/{$target->id}/status", ['is_active' => false])->assertOk();
        $this->patchJson("/api/internal/users/{$target->id}/status", ['is_active' => true])->assertOk();

        $this->getJson("/api/internal/activity-logs?user_id={$target->id}")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.description', "Mengaktifkan akun {$target->email}")
            ->assertJsonPath('data.0.subject_type', 'user')
            ->assertJsonPath('data.0.causer.id', $this->superAdmin->id)
            ->assertJsonPath('data.1.changes.attributes.is_active', false);

        $this->getJson('/api/internal/activity-logs?event=user.created')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs(User::factory()->facultyAdmin()->create());
        $this->getJson('/api/internal/activity-logs')->assertForbidden();
    }
}
