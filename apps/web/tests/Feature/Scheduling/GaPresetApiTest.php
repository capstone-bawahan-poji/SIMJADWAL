<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Database\Seeders\Reference\GaPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class GaPresetApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Faculty $faculty;

    private StudyProgram $program;

    private User $facultyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->facultyAdmin = User::factory()->facultyAdmin($this->faculty)->create();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Eksplorasi Luas',
            'population_size' => 100,
            'min_generations' => 500,
            'max_generations' => 1000,
            'stagnation_limit' => 200,
            'crossover_rate' => 0.9,
            'mutation_rate' => 0.5,
            'tournament_size' => 2,
            'selection_operator' => 'TS',
            'parallel_class_limit' => 5,
            'fitness_constant' => 500,
            'base_seed' => 12345,
            'run_count' => 10,
            'sc_overrides' => [(string) $this->program->id => ['SC_INGIN' => 2]],
            ...$overrides,
        ];
    }

    public function test_faculty_admin_creates_lists_updates_and_deletes_own_presets(): void
    {
        Sanctum::actingAs($this->facultyAdmin);

        $id = $this->postJson('/api/v1/ga-presets', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.faculty_id', $this->faculty->id)
            ->assertJsonPath('data.crossover_rate', 0.9)
            ->assertJsonPath('data.fitness_constant', 500)
            ->assertJsonPath("data.sc_overrides.{$this->program->id}.SC_INGIN", 2)
            ->assertJsonPath('data.can_update', true)
            ->json('data.id');

        $this->getJson('/api/v1/ga-presets')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/ga-presets/{$id}", $this->payload(['name' => 'Konvergensi Cepat', 'sc_overrides' => []]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Konvergensi Cepat')
            ->assertJsonPath('data.sc_overrides', []);

        $this->deleteJson("/api/v1/ga-presets/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('ga_parameter_presets', ['id' => $id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'ga_preset.created']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'ga_preset.updated']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'ga_preset.deleted']);
    }

    public function test_hard_constraints_cannot_be_overridden_and_weights_must_be_positive(): void
    {
        Sanctum::actingAs($this->facultyAdmin);
        $key = "sc_overrides.{$this->program->id}";

        $this->postJson('/api/v1/ga-presets', $this->payload(['sc_overrides' => [$this->program->id => ['HC2' => 5]]]))
            ->assertUnprocessable()->assertJsonValidationErrors("{$key}.HC2");
        $this->postJson('/api/v1/ga-presets', $this->payload(['sc_overrides' => [$this->program->id => ['SC_SKS' => 0]]]))
            ->assertUnprocessable()->assertJsonValidationErrors("{$key}.SC_SKS");

        $foreign = StudyProgram::factory()->create();
        $this->postJson('/api/v1/ga-presets', $this->payload(['sc_overrides' => [$foreign->id => ['SC_SKS' => 1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors("sc_overrides.{$foreign->id}");
    }

    public function test_numbers_follow_the_schema_rules(): void
    {
        Sanctum::actingAs($this->facultyAdmin);

        $this->postJson('/api/v1/ga-presets', $this->payload(['max_generations' => 400]))
            ->assertUnprocessable()->assertJsonValidationErrors('max_generations');
        $this->postJson('/api/v1/ga-presets', $this->payload(['run_count' => 11]))
            ->assertUnprocessable()->assertJsonValidationErrors('run_count');
        $this->postJson('/api/v1/ga-presets', $this->payload(['tournament_size' => 1, 'crossover_rate' => 1.5]))
            ->assertUnprocessable()->assertJsonValidationErrors(['tournament_size', 'crossover_rate']);
    }

    public function test_names_are_unique_per_faculty_only(): void
    {
        $other = Faculty::factory()->create();
        GaParameterPreset::query()->create([...$this->payload(['sc_overrides' => []]), 'faculty_id' => $other->id]);

        Sanctum::actingAs($this->facultyAdmin);
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertCreated();
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_presets_of_another_faculty_are_off_limits(): void
    {
        $preset = GaParameterPreset::query()->create([...$this->payload(['sc_overrides' => []]), 'faculty_id' => Faculty::factory()->create()->id]);

        Sanctum::actingAs($this->facultyAdmin);
        $this->getJson('/api/v1/ga-presets')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/ga-presets/{$preset->id}")->assertForbidden();
        $this->putJson("/api/v1/ga-presets/{$preset->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/ga-presets/{$preset->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());
        $this->getJson('/api/v1/ga-presets')->assertForbidden();
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertForbidden();
    }

    public function test_superadmin_works_on_a_chosen_faculty(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/ga-presets')->assertUnprocessable()->assertJsonValidationErrors('faculty_id');
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('faculty_id');

        $this->postJson('/api/v1/ga-presets', $this->payload(['faculty_id' => $this->faculty->id]))
            ->assertCreated()->assertJsonPath('data.faculty_id', $this->faculty->id);
        $this->getJson("/api/v1/ga-presets?faculty_id={$this->faculty->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_preset_used_by_a_run_cannot_be_deleted(): void
    {
        $preset = GaParameterPreset::query()->create([...$this->payload(['sc_overrides' => []]), 'faculty_id' => $this->faculty->id]);
        SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $this->faculty->id,
            'period' => '2026/2027-GANJIL',
            'preset_id' => $preset->id,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'triggered_by' => $this->facultyAdmin->id,
        ]);

        Sanctum::actingAs($this->facultyAdmin);
        $this->deleteJson("/api/v1/ga-presets/{$preset->id}")->assertStatus(409);
        $this->assertDatabaseHas('ga_parameter_presets', ['id' => $preset->id]);
    }

    public function test_seeder_adds_the_two_laporan_ta_presets_per_faculty(): void
    {
        $this->seed(GaPresetSeeder::class);
        $this->seed(GaPresetSeeder::class);

        $this->assertSame(2, GaParameterPreset::query()->where('faculty_id', $this->faculty->id)->count());
        $this->assertDatabaseHas('ga_parameter_presets', ['faculty_id' => $this->faculty->id, 'name' => 'Preset 2 (TSR)', 'selection_operator' => 'TSR']);
    }
}
