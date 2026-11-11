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

    private User $programAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->programAdmin = User::factory()->studyProgramAdmin($this->program)->create();
    }

    /**
     * @return array{faculty_id: int, study_program_id: int}
     */
    private function owner(StudyProgram $program): array
    {
        return ['faculty_id' => $program->faculty_id, 'study_program_id' => $program->id];
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

    public function test_program_admin_creates_lists_updates_and_deletes_own_presets(): void
    {
        Sanctum::actingAs($this->programAdmin);

        $id = $this->postJson('/api/v1/ga-presets', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.faculty_id', $this->faculty->id)
            ->assertJsonPath('data.study_program_id', $this->program->id)
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
        Sanctum::actingAs($this->programAdmin);
        $key = "sc_overrides.{$this->program->id}";

        $this->postJson('/api/v1/ga-presets', $this->payload(['sc_overrides' => [$this->program->id => ['HC2' => 5]]]))
            ->assertUnprocessable()->assertJsonValidationErrors("{$key}.HC2");
        $this->postJson('/api/v1/ga-presets', $this->payload(['sc_overrides' => [$this->program->id => ['SC_SKS' => 0]]]))
            ->assertUnprocessable()->assertJsonValidationErrors("{$key}.SC_SKS");

        $foreign = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->postJson('/api/v1/ga-presets', $this->payload(['sc_overrides' => [$foreign->id => ['SC_SKS' => 1]]]))
            ->assertUnprocessable()->assertJsonValidationErrors("sc_overrides.{$foreign->id}");
    }

    public function test_numbers_follow_the_schema_rules(): void
    {
        Sanctum::actingAs($this->programAdmin);

        $this->postJson('/api/v1/ga-presets', $this->payload(['max_generations' => 400]))
            ->assertUnprocessable()->assertJsonValidationErrors('max_generations');
        $this->postJson('/api/v1/ga-presets', $this->payload(['run_count' => 11]))
            ->assertUnprocessable()->assertJsonValidationErrors('run_count');
        $this->postJson('/api/v1/ga-presets', $this->payload(['tournament_size' => 1, 'crossover_rate' => 1.5]))
            ->assertUnprocessable()->assertJsonValidationErrors(['tournament_size', 'crossover_rate']);
    }

    public function test_names_are_unique_per_program_only(): void
    {
        $other = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        GaParameterPreset::query()->create([...$this->payload(['sc_overrides' => []]), ...$this->owner($other)]);

        Sanctum::actingAs($this->programAdmin);
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertCreated();
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_presets_of_another_program_are_off_limits(): void
    {
        $neighbour = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $preset = GaParameterPreset::query()->create([...$this->payload(['sc_overrides' => []]), ...$this->owner($neighbour)]);

        Sanctum::actingAs($this->programAdmin);
        $this->getJson('/api/v1/ga-presets')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/ga-presets/{$preset->id}")->assertForbidden();
        $this->putJson("/api/v1/ga-presets/{$preset->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/ga-presets/{$preset->id}")->assertForbidden();

        // The faculty reads its programs' presets but no longer writes any.
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->getJson("/api/v1/ga-presets?study_program_id={$neighbour->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/ga-presets')->assertUnprocessable()->assertJsonValidationErrors('study_program_id');
        $this->postJson('/api/v1/ga-presets', $this->payload(['study_program_id' => $neighbour->id]))->assertForbidden();
        $this->getJson('/api/v1/ga-presets?study_program_id='.StudyProgram::factory()->create()->id)->assertForbidden();
    }

    public function test_superadmin_works_on_a_chosen_program(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/ga-presets')->assertUnprocessable()->assertJsonValidationErrors('study_program_id');
        $this->postJson('/api/v1/ga-presets', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('study_program_id');

        $this->postJson('/api/v1/ga-presets', $this->payload(['study_program_id' => $this->program->id]))
            ->assertCreated()->assertJsonPath('data.study_program_id', $this->program->id)->assertJsonPath('data.faculty_id', $this->faculty->id);
        $this->getJson("/api/v1/ga-presets?study_program_id={$this->program->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_preset_used_by_a_run_cannot_be_deleted(): void
    {
        $preset = GaParameterPreset::query()->create([...$this->payload(['sc_overrides' => []]), ...$this->owner($this->program)]);
        SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            ...$this->owner($this->program),
            'period' => '2026/2027-GANJIL',
            'preset_id' => $preset->id,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'triggered_by' => $this->programAdmin->id,
        ]);

        Sanctum::actingAs($this->programAdmin);
        $this->deleteJson("/api/v1/ga-presets/{$preset->id}")->assertStatus(409);
        $this->assertDatabaseHas('ga_parameter_presets', ['id' => $preset->id]);
    }

    public function test_seeder_adds_the_two_laporan_ta_presets_per_program(): void
    {
        $this->seed(GaPresetSeeder::class);
        $this->seed(GaPresetSeeder::class);

        $this->assertSame(2, GaParameterPreset::query()->where('study_program_id', $this->program->id)->count());
        $this->assertDatabaseHas('ga_parameter_presets', [...$this->owner($this->program), 'name' => 'Preset 2 (TSR)', 'selection_operator' => 'TSR']);
    }

    public function test_tpb_admin_lists_and_creates_tpb_presets_without_faculty(): void
    {
        $this->seed(GaPresetSeeder::class);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());

        $this->getJson('/api/v1/ga-presets')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.faculty_id', null);
    }
}
