<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class SchedulingDatasetApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $accepted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->accepted = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id]);
    }

    /** A course of the program with one class taught by a lecturer of the same program. */
    private function courseWithClass(StudyProgram $program, array $attributes = []): Course
    {
        $course = Course::factory()->create(['study_program_id' => $program->id, 'semester' => 3, ...$attributes]);
        CourseLecturer::factory()->create([
            'course_id' => $course->id,
            'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create(['study_program_id' => $program->id])->id,
        ]);

        return $course;
    }

    private function getDataset(array $query = []): TestResponse
    {
        return $this->getJson('/api/v1/scheduling/dataset?'.http_build_query(['period' => self::PERIOD, ...$query]));
    }

    public function test_faculty_admin_gets_own_faculty_with_only_accepted_programs_contributing(): void
    {
        $draft = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $submitted = StudyProgram::factory()->submitted()->create(['faculty_id' => $this->faculty->id]);
        $course = $this->courseWithClass($this->accepted);
        $this->courseWithClass($draft);
        $this->courseWithClass($submitted);

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $response = $this->getDataset(['faculty_id' => Faculty::factory()->create()->id])
            ->assertOk()
            ->assertJsonPath('data.faculty.id', $this->faculty->id)
            ->assertJsonPath('data.period', self::PERIOD)
            ->assertJsonCount(3, 'data.study_programs')
            ->assertJsonCount(1, 'data.courses')
            ->assertJsonPath('data.courses.0.id', $course->id)
            ->assertJsonCount(1, 'data.classes')
            ->assertJsonCount(1, 'data.lecturers')
            ->assertJsonCount(7, 'data.constraint_types');

        $included = collect($response->json('data.study_programs'))->pluck('included', 'id');
        $this->assertTrue($included[$this->accepted->id]);
        $this->assertFalse($included[$draft->id]);
        $this->assertFalse($included[$submitted->id]);
        $this->assertStringStartsWith('sha256:', $response->json('data.fingerprint'));
    }

    public function test_courses_follow_semester_parity_and_skip_tpb(): void
    {
        $odd = $this->courseWithClass($this->accepted, ['semester' => 5]);
        $even = $this->courseWithClass($this->accepted, ['semester' => 4]);
        Course::factory()->tpb()->create();

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->getDataset()->assertOk()
            ->assertJsonCount(1, 'data.courses')->assertJsonPath('data.courses.0.id', $odd->id);
        $this->getDataset(['period' => '2026/2027-GENAP'])->assertOk()
            ->assertJsonCount(1, 'data.courses')->assertJsonPath('data.courses.0.id', $even->id);
    }

    public function test_lecturer_preferences_and_shared_rooms_are_included(): void
    {
        $course = $this->courseWithClass($this->accepted);
        $lecturer = $course->courseLecturers()->firstOrFail()->lecturer;
        [$want, $avoid] = [TimeSlot::factory()->create(['session' => 1]), TimeSlot::factory()->create(['session' => 2])];
        $types = ConstraintType::query()->pluck('id', 'code');
        LecturerPreference::query()->create(['lecturer_id' => $lecturer->id, 'constraint_type_id' => $types['SC_INGIN'], 'time_slot_id' => $want->id]);
        LecturerPreference::query()->create(['lecturer_id' => $lecturer->id, 'constraint_type_id' => $types['SC_HINDARI'], 'time_slot_id' => $avoid->id]);

        $shared = Room::factory()->create(['faculty_id' => null]);
        $own = Room::factory()->create(['faculty_id' => $this->faculty->id]);
        Room::factory()->create(['faculty_id' => Faculty::factory()->create()->id]);

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $response = $this->getDataset()->assertOk()
            ->assertJsonCount(2, 'data.time_slots')
            ->assertJsonPath('data.lecturers.0.preferences.0.type', 'want')
            ->assertJsonPath('data.lecturers.0.preferences.1.type', 'avoid');

        $this->assertEqualsCanonicalizing([$shared->id, $own->id], collect($response->json('data.rooms'))->pluck('id')->all());
    }

    public function test_occupied_rooms_come_only_from_active_runs_of_other_faculties_in_the_same_period(): void
    {
        $slot = TimeSlot::factory()->create();
        $room = Room::factory()->create(['faculty_id' => null]);
        $other = Faculty::factory()->create();
        $course = $this->courseWithClass($this->accepted);
        $lecturerId = $course->courseLecturers()->firstOrFail()->lecturer_id;
        $user = User::factory()->facultyAdmin($this->faculty)->create();

        $run = fn (int $facultyId, string $period, bool $active) => SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $facultyId,
            'period' => $period,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'is_active' => $active,
            'triggered_by' => $user->id,
        ]);
        $detail = fn (SchedulingRun $run) => ScheduleDetail::query()->create([
            'scheduling_run_id' => $run->id,
            'course_id' => $course->id,
            'class_number' => 1,
            'meeting_number' => 1,
            'lecturer_id' => $lecturerId,
            'time_slot_id' => $slot->id,
            'room_id' => $room->id,
        ]);

        $detail($run($other->id, self::PERIOD, true));
        $detail($run($other->id, self::PERIOD, false));
        $detail($run($other->id, '2025/2026-GANJIL', true));
        $detail($run($this->faculty->id, '2025/2026-GENAP', true));

        Sanctum::actingAs($user);

        $this->getDataset()->assertOk()
            ->assertJsonCount(1, 'data.occupied_rooms')
            ->assertJsonPath('data.occupied_rooms.0', ['time_slot_id' => $slot->id, 'room_id' => $room->id]);
    }

    public function test_fingerprint_is_stable_and_changes_when_a_preference_changes(): void
    {
        $course = $this->courseWithClass($this->accepted);
        $slot = TimeSlot::factory()->create();

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $first = $this->getDataset()->json('data.fingerprint');
        $this->assertSame($first, $this->getDataset()->json('data.fingerprint'));

        LecturerPreference::query()->create([
            'lecturer_id' => $course->courseLecturers()->firstOrFail()->lecturer_id,
            'constraint_type_id' => ConstraintType::query()->where('code', 'SC_INGIN')->value('id'),
            'time_slot_id' => $slot->id,
        ]);

        $this->assertNotSame($first, $this->getDataset()->json('data.fingerprint'));
    }

    public function test_period_defaults_to_config_and_rejects_bad_format(): void
    {
        config(['scheduling.current_period' => '2027/2028-GENAP']);
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->getJson('/api/v1/scheduling/dataset')->assertOk()->assertJsonPath('data.period', '2027/2028-GENAP');
        $this->getDataset(['period' => '2026-GANJIL'])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->getDataset(['period' => '2026/2028-GANJIL'])->assertUnprocessable()->assertJsonValidationErrors('period');
    }

    public function test_superadmin_must_pick_a_faculty(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getDataset()->assertUnprocessable()->assertJsonValidationErrors('faculty_id');
        $this->getDataset(['faculty_id' => $this->faculty->id])->assertOk()->assertJsonPath('data.faculty.id', $this->faculty->id);
    }

    public function test_other_roles_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->accepted)->create());
        $this->getDataset()->assertForbidden();

        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->getDataset()->assertForbidden();
    }

    public function test_desktop_reads_constraint_submissions_and_types_through_v1(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->getJson('/api/v1/constraint-types')->assertOk()->assertJsonCount(7, 'data');
        $this->getJson('/api/v1/constraint-submissions')->assertOk()->assertJsonPath('data.0.id', $this->accepted->id);
        $this->getJson("/api/v1/constraint-submissions/{$this->accepted->id}")->assertOk()->assertJsonPath('data.summary.id', $this->accepted->id);
        $this->postJson("/api/v1/constraint-submissions/{$this->accepted->id}/accept")->assertNotFound();
    }
}
