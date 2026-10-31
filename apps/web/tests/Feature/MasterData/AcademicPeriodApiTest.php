<?php

namespace Tests\Feature\MasterData;

use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\AcademicPeriod;
use App\Models\MasterData\Faculty;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class AcademicPeriodApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        config(['scheduling.current_period' => '2026/2027-GANJIL']);
        Sanctum::actingAs(User::factory()->superAdmin()->create());
    }

    public function test_superadmin_creates_period_with_valid_code_and_dates(): void
    {
        $this->postJson('/api/v1/academic-periods', ['code' => '2026/2027-GANJIL', 'start_date' => '2026-08-31', 'end_date' => '2026-12-18'])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Ganjil 2026/2027')
            ->assertJsonPath('data.start_date', '2026-08-31')
            ->assertJsonPath('data.end_date', '2026-12-18')
            ->assertJsonPath('data.is_current', true);

        $this->postJson('/api/v1/academic-periods', ['code' => '2026/2027-GANJIL', 'start_date' => '2026-12-18', 'end_date' => '2026-08-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'end_date']);
        $this->postJson('/api/v1/academic-periods', ['code' => '2026/2028-GENAP', 'start_date' => '2027-02-08', 'end_date' => '2027-06-04'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_patch_checks_merged_dates(): void
    {
        $period = AcademicPeriod::factory()->create();

        $this->patchJson("/api/v1/academic-periods/{$period->id}", ['end_date' => '2026-08-01'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->patchJson("/api/v1/academic-periods/{$period->id}", ['end_date' => '2026-12-25'])->assertOk()->assertJsonPath('data.end_date', '2026-12-25');
    }

    public function test_period_used_by_runs_keeps_its_code_but_dates_may_change(): void
    {
        $period = AcademicPeriod::factory()->create();
        SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => Faculty::factory()->create()->id,
            'period' => $period->code,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'triggered_by' => User::factory()->create()->id,
        ]);

        $this->patchJson("/api/v1/academic-periods/{$period->id}", ['start_date' => '2026-09-07'])->assertOk();
        $this->patchJson("/api/v1/academic-periods/{$period->id}", ['code' => '2027/2028-GANJIL'])
            ->assertStatus(409)
            ->assertJsonPath('errors.references.scheduling_runs', 1);
        $this->deleteJson("/api/v1/academic-periods/{$period->id}")->assertStatus(409);
    }

    public function test_everyone_reads_periods_but_only_superadmin_writes(): void
    {
        AcademicPeriod::factory()->create();
        AcademicPeriod::factory()->create(['code' => '2027/2028-GANJIL', 'start_date' => '2027-08-30', 'end_date' => '2027-12-17']);
        Sanctum::actingAs(User::factory()->student()->create());

        $this->getJson('/api/v1/academic-periods')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', '2027/2028-GANJIL');
        $this->postJson('/api/v1/academic-periods', [])->assertForbidden();
    }

    public function test_superadmin_deletes_unused_period(): void
    {
        $period = AcademicPeriod::factory()->create();

        $this->deleteJson("/api/v1/academic-periods/{$period->id}")->assertNoContent();
        $this->assertModelMissing($period);
    }
}
