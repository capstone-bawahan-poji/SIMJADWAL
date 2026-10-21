<?php

namespace Tests\Feature\Scheduling;

use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\GaParameterPreset;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class SchedulingRunApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $program;

    private User $admin;

    /** @var list<TimeSlot> */
    private array $slots;

    private Room $room;

    private Course $threeSks;

    private Course $fourSks;

    private Lecturer $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id]);
        $this->admin = User::factory()->facultyAdmin($this->faculty)->create();
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 1, 'session' => $session]), [1, 2, 3, 4]);
        $this->room = Room::factory()->create(['faculty_id' => null, 'capacity' => 50]);
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);

        $this->threeSks = Course::factory()->create(['study_program_id' => $this->program->id, 'sks' => 3, 'semester' => 3, 'class_capacity' => 50]);
        $this->fourSks = Course::factory()->create(['study_program_id' => $this->program->id, 'sks' => 4, 'semester' => 3, 'class_capacity' => 50]);
        foreach ([$this->threeSks, $this->fourSks] as $course) {
            CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id]);
        }

        Sanctum::actingAs($this->admin);
    }

    private function fingerprint(): string
    {
        return $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))->json('data.fingerprint');
    }

    /** A clean schedule: three genes in three different slots, no hard violation. */
    private function payload(array $overrides = []): array
    {
        return [
            'client_run_id' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'period' => self::PERIOD,
            'dataset_fingerprint' => $this->fingerprint(),
            'preset_id' => null,
            'parameter_snapshot' => [
                'population_size' => 100, 'parallel_class_limit' => 5, 'fitness_constant' => 500, 'seed' => 12345,
                'selection_operator' => 'TS', 'crossover_rate' => 0.9, 'mutation_rate' => 0.5,
                'weights' => ['HC1' => 10, 'HC2' => 10, 'HC3' => 10, 'HC4' => 10, 'SC_INGIN' => 1, 'SC_HINDARI' => 1, 'SC_SKS' => 2],
            ],
            'best_fitness' => 506,
            'max_fitness' => 506,
            'best_generation' => 394,
            'final_generation' => 594,
            'fitness_history' => [480, 500, 506],
            'started_at' => '2026-10-19T10:00:00+08:00',
            'finished_at' => '2026-10-19T10:00:01+08:00',
            'schedule_details' => [
                $this->gene($this->threeSks, 1, 0, $this->room->id),
                $this->gene($this->fourSks, 1, 1, null),
                $this->gene($this->fourSks, 2, 3, null),
            ],
            'violations' => [],
            ...$overrides,
        ];
    }

    private function gene(Course $course, int $meeting, int $slot, ?int $roomId): array
    {
        return [
            'course_id' => $course->id,
            'class_number' => 1,
            'meeting_number' => $meeting,
            'lecturer_id' => $this->lecturer->id,
            'time_slot_id' => $this->slots[$slot]->id,
            'room_id' => $roomId,
        ];
    }

    public function test_upload_stores_run_details_and_violations(): void
    {
        $payload = $this->payload(['violations' => [
            ['detail_index' => 1, 'constraint_code' => 'SC_INGIN', 'penalty' => 1, 'message' => 'Bukan slot yang diinginkan'],
        ]]);

        $id = $this->postJson('/api/v1/scheduling-runs', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.schedule_details_count', 3)
            ->assertJsonPath('data.sc_unmet', 1)
            ->assertJsonPath('data.hc_violations', 0)
            ->assertJsonPath('data.unassigned_rooms', 2)
            ->assertJsonPath('data.selection_operator', 'TS')
            ->assertJsonPath('data.is_active', false)
            ->json('data.id');

        $run = SchedulingRun::query()->findOrFail($id);
        $this->assertSame($payload['client_run_id'], $run->client_run_id);
        $this->assertSame(594, $run->current_generation);
        $this->assertSame($this->admin->id, $run->triggered_by);
        $this->assertDatabaseHas('violation_logs', ['scheduling_run_id' => $id, 'message' => 'Bukan slot yang diinginkan']);

        $this->getJson("/api/v1/scheduling-runs/{$id}")->assertOk()->assertJsonPath('data.fitness_history', [480, 500, 506]);
        $this->getJson("/api/v1/scheduling-runs/{$id}/schedule-details")->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.room.id', $this->room->id)
            ->assertJsonPath('data.0.course.study_program.id', $this->program->id);
        $this->getJson("/api/v1/scheduling-runs/{$id}/violations?category=SC")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/scheduling-runs?period='.urlencode(self::PERIOD))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_web_reads_runs_through_the_session_api(): void
    {
        $id = $this->postJson('/api/v1/scheduling-runs', $this->payload())->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->getJson('/api/internal/scheduling-runs')->assertOk()->assertJsonPath('data.0.id', $id);
    }

    public function test_uploading_the_same_client_run_id_twice_does_not_duplicate(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/v1/scheduling-runs', $payload)->assertCreated();
        $this->postJson('/api/v1/scheduling-runs', $payload)->assertOk();

        $this->assertSame(1, SchedulingRun::query()->count());
    }

    public function test_run_from_stale_data_is_refused(): void
    {
        $payload = $this->payload();
        LecturerPreference::query()->create([
            'lecturer_id' => $this->lecturer->id,
            'constraint_type_id' => ConstraintType::query()->where('code', 'SC_INGIN')->value('id'),
            'time_slot_id' => $this->slots[0]->id,
        ]);

        $this->postJson('/api/v1/scheduling-runs', $payload)->assertStatus(409)->assertJsonPath('code', 'DATASET_CHANGED');
        $this->assertSame(0, SchedulingRun::query()->count());
    }

    public function test_schedule_must_cover_exactly_the_dataset_classes(): void
    {
        $missing = $this->payload();
        array_pop($missing['schedule_details']);
        $this->postJson('/api/v1/scheduling-runs', $missing)->assertUnprocessable()->assertJsonValidationErrors('schedule_details');

        $duplicate = $this->payload();
        $duplicate['schedule_details'][2] = $duplicate['schedule_details'][1];
        $this->postJson('/api/v1/scheduling-runs', $duplicate)->assertUnprocessable()->assertJsonValidationErrors('schedule_details');

        $wrongLecturer = $this->payload();
        $wrongLecturer['schedule_details'][0]['lecturer_id'] = Lecturer::factory()->create()->id;
        $this->postJson('/api/v1/scheduling-runs', $wrongLecturer)->assertUnprocessable()->assertJsonValidationErrors('schedule_details.0.lecturer_id');
    }

    public function test_rooms_must_fit_and_be_free(): void
    {
        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 20]);
        $payload = $this->payload();
        $payload['schedule_details'][1]['room_id'] = $small->id;
        $this->postJson('/api/v1/scheduling-runs', $payload)->assertUnprocessable()->assertJsonValidationErrors('schedule_details.1.room_id');

        $foreign = Room::factory()->create(['faculty_id' => Faculty::factory()->create()->id, 'capacity' => 50]);
        $payload = $this->payload();
        $payload['schedule_details'][1]['room_id'] = $foreign->id;
        $this->postJson('/api/v1/scheduling-runs', $payload)->assertUnprocessable()->assertJsonValidationErrors('schedule_details.1.room_id');
    }

    public function test_hard_constraint_counts_must_match_the_server(): void
    {
        // Both meetings of the 4 SKS course in the same slot: one HC2 and one HC3.
        $clash = $this->payload();
        $clash['schedule_details'][2]['time_slot_id'] = $this->slots[1]->id;

        $this->postJson('/api/v1/scheduling-runs', $clash)
            ->assertUnprocessable()->assertJsonValidationErrors(['violations.HC2', 'violations.HC3']);

        $clash['violations'] = [
            ['detail_index' => 2, 'constraint_code' => 'HC2', 'penalty' => 10, 'message' => 'Dosen bentrok'],
            ['detail_index' => 2, 'constraint_code' => 'HC3', 'penalty' => 10, 'message' => 'Kelas bentrok'],
        ];
        $this->postJson('/api/v1/scheduling-runs', $clash)->assertCreated()->assertJsonPath('data.hc_violations', 2);
    }

    public function test_preset_of_another_faculty_is_refused(): void
    {
        $preset = GaParameterPreset::query()->create([
            'faculty_id' => Faculty::factory()->create()->id, 'name' => 'Lain', 'crossover_rate' => 0.9,
            'mutation_rate' => 0.5, 'selection_operator' => 'TS',
        ]);

        $this->postJson('/api/v1/scheduling-runs', $this->payload(['preset_id' => $preset->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('preset_id');
    }

    public function test_other_faculties_and_roles_cannot_read_or_upload(): void
    {
        $id = $this->postJson('/api/v1/scheduling-runs', $this->payload())->assertCreated()->json('data.id');

        Sanctum::actingAs(User::factory()->facultyAdmin(Faculty::factory()->create())->create());
        $this->getJson("/api/v1/scheduling-runs/{$id}")->assertForbidden();
        $this->getJson('/api/v1/scheduling-runs')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());
        $this->getJson('/api/v1/scheduling-runs')->assertForbidden();
        $this->postJson('/api/v1/scheduling-runs', [])->assertForbidden();
    }

    public function test_superadmin_uploads_for_a_chosen_faculty(): void
    {
        $payload = $this->payload();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/scheduling-runs', $payload)->assertUnprocessable()->assertJsonValidationErrors('faculty_id');
        $this->postJson('/api/v1/scheduling-runs', [...$payload, 'faculty_id' => $this->faculty->id])
            ->assertCreated()->assertJsonPath('data.faculty.id', $this->faculty->id);
    }
}
