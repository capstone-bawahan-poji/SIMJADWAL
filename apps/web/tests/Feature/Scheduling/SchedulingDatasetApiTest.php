<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\SubmissionStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\SchedulePublication;
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\TpbOccupancyImport;
use App\Models\Scheduling\TpbOccupancyImportRow;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProgramSchedules;
use Tests\Concerns\CreatesTpbRun;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class SchedulingDatasetApiTest extends TestCase
{
    use BuildsProgramSchedules, CreatesTpbRun, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $program;

    private TimeSlot $slot;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);
        $this->activeTpbRun(self::PERIOD);

        $this->faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->slot = TimeSlot::factory()->create(['day' => 1, 'session' => 1]);
        $this->room = Room::factory()->create(['faculty_id' => $this->faculty->id, 'capacity' => 60]);
        $this->giveCells($this->program, self::PERIOD, [[$this->slot, $this->room]]);
    }

    /** A course of the program with one class taught by a lecturer of the same program. */
    private function courseWithClass(StudyProgram $program, array $attributes = []): Course
    {
        $course = Course::factory()->create(['study_program_id' => $program->id, 'semester' => 3, 'class_capacity' => 40, ...$attributes]);
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

    public function test_program_admin_gets_own_program_with_its_cells(): void
    {
        $course = $this->courseWithClass($this->program);
        $this->courseWithClass(StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]));

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $response = $this->getDataset(['study_program_id' => StudyProgram::factory()->create()->id])
            ->assertOk()
            ->assertJsonPath('data.scope', 'study_program')
            ->assertJsonPath('data.study_program.id', $this->program->id)
            ->assertJsonPath('data.faculty.id', $this->faculty->id)
            ->assertJsonPath('data.period', self::PERIOD)
            ->assertJsonCount(1, 'data.courses')
            ->assertJsonPath('data.courses.0.id', $course->id)
            ->assertJsonCount(1, 'data.classes')
            ->assertJsonCount(1, 'data.lecturers')
            ->assertJsonPath('data.cells', [['time_slot_id' => $this->slot->id, 'room_id' => $this->room->id]])
            ->assertJsonPath('data.rooms.0.id', $this->room->id)
            ->assertJsonPath('data.tpb_source.type', 'run')
            ->assertJsonCount(8, 'data.constraint_types');

        $this->assertStringStartsWith('sha256:', $response->json('data.fingerprint'));
    }

    public function test_courses_follow_semester_parity_and_skip_inactive_and_tpb(): void
    {
        $odd = $this->courseWithClass($this->program, ['semester' => 5]);
        $even = $this->courseWithClass($this->program, ['semester' => 4]);
        $this->courseWithClass($this->program, ['semester' => 5, 'is_active' => false]);
        Course::factory()->tpb()->create();
        $this->giveCells($this->program, '2026/2027-GENAP', [[$this->slot, $this->room]]);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getDataset()->assertOk()
            ->assertJsonCount(1, 'data.courses')->assertJsonPath('data.courses.0.id', $odd->id);
        $this->activeTpbRun('2026/2027-GENAP');
        $this->getDataset(['period' => '2026/2027-GENAP'])->assertOk()
            ->assertJsonCount(1, 'data.courses')->assertJsonPath('data.courses.0.id', $even->id);
    }

    public function test_lecturer_preferences_are_included(): void
    {
        $course = $this->courseWithClass($this->program);
        $lecturer = $course->courseLecturers()->firstOrFail()->lecturer;
        $avoid = TimeSlot::factory()->create(['day' => 1, 'session' => 2]);
        $types = ConstraintType::query()->pluck('id', 'code');
        LecturerPreference::query()->create(['lecturer_id' => $lecturer->id, 'constraint_type_id' => $types['SC_INGIN'], 'time_slot_id' => $this->slot->id]);
        LecturerPreference::query()->create(['lecturer_id' => $lecturer->id, 'constraint_type_id' => $types['SC_HINDARI'], 'time_slot_id' => $avoid->id]);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getDataset()->assertOk()
            ->assertJsonCount(2, 'data.time_slots')
            ->assertJsonPath('data.lecturers.0.preferences.0.type', 'want')
            ->assertJsonPath('data.lecturers.0.preferences.1.type', 'avoid');
    }

    public function test_lecturer_busy_lists_active_runs_of_other_programs_only(): void
    {
        $course = $this->courseWithClass($this->program);
        $lecturerId = $course->courseLecturers()->firstOrFail()->lecturer_id;
        $other = StudyProgram::factory()->create();
        $otherCourse = $this->courseWithClass($other);
        $busy = TimeSlot::factory()->create(['day' => 2, 'session' => 1]);
        $gene = ['course_id' => $otherCourse->id, 'class_number' => 1, 'lecturer_id' => $lecturerId, 'time_slot_id' => $busy->id, 'room_id' => null];

        $this->programRun($other, self::PERIOD, [$gene]);
        $this->programRun($other, self::PERIOD, [$gene], active: false);
        $this->programRun($other, '2025/2026-GANJIL', [$gene]);
        $this->programRun($this->program, self::PERIOD, [[...$gene, 'course_id' => $course->id, 'time_slot_id' => $this->slot->id]]);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getDataset()->assertOk()
            ->assertJsonPath('data.lecturer_busy', [['lecturer_id' => $lecturerId, 'time_slot_ids' => [$busy->id]]]);
    }

    public function test_fingerprint_is_stable_and_changes_with_preferences_and_cells(): void
    {
        $course = $this->courseWithClass($this->program);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $first = $this->getDataset()->json('data.fingerprint');
        $this->assertNotNull($first);
        $this->assertSame($first, $this->getDataset()->json('data.fingerprint'));

        LecturerPreference::query()->create([
            'lecturer_id' => $course->courseLecturers()->firstOrFail()->lecturer_id,
            'constraint_type_id' => ConstraintType::query()->where('code', 'SC_INGIN')->value('id'),
            'time_slot_id' => $this->slot->id,
        ]);
        $second = $this->getDataset()->json('data.fingerprint');
        $this->assertNotSame($first, $second);

        $this->giveCells($this->program, self::PERIOD, [[TimeSlot::factory()->create(['day' => 3, 'session' => 1]), $this->room]]);
        $this->assertNotSame($second, $this->getDataset()->json('data.fingerprint'));
    }

    public function test_period_defaults_to_config_and_rejects_bad_format(): void
    {
        config(['scheduling.current_period' => '2027/2028-GENAP']);
        $this->activeTpbRun('2027/2028-GENAP');
        $this->courseWithClass($this->program, ['semester' => 4]);
        $this->giveCells($this->program, '2027/2028-GENAP', [[$this->slot, $this->room]]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getJson('/api/v1/scheduling/dataset')->assertOk()->assertJsonPath('data.period', '2027/2028-GENAP');
        $this->getDataset(['period' => '2026-GANJIL'])->assertUnprocessable()->assertJsonValidationErrors('period');
        $this->getDataset(['period' => '2026/2028-GANJIL'])->assertUnprocessable()->assertJsonValidationErrors('period');
    }

    public function test_superadmin_must_pick_a_program(): void
    {
        $this->courseWithClass($this->program);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getDataset()->assertUnprocessable()->assertJsonValidationErrors('study_program_id');
        $this->getDataset(['study_program_id' => $this->program->id])->assertOk()->assertJsonPath('data.study_program.id', $this->program->id);
        $this->getDataset(['scope' => 'tpb'])->assertOk()->assertJsonPath('data.scope', 'tpb');
    }

    public function test_faculty_admin_no_longer_gets_a_dataset_and_tpb_admin_gets_tpb(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->getDataset(['study_program_id' => $this->program->id])->assertForbidden();

        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->getDataset(['study_program_id' => $this->program->id])->assertOk()->assertJsonPath('data.scope', 'tpb')->assertJsonPath('data.faculty', null);
    }

    public function test_dataset_needs_tpb_data_cells_lecturers_and_a_draft_schedule(): void
    {
        $course = $this->courseWithClass($this->program);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        // No TPB data in the period.
        $this->getDataset(['period' => '2030/2031-GANJIL'])->assertStatus(409);

        // A faculty import counts as TPB data; the program still needs cells.
        $import = TpbOccupancyImport::query()->create(['faculty_id' => $this->faculty->id, 'period' => '2030/2031-GANJIL', 'imported_by' => User::factory()->create()->id]);
        TpbOccupancyImportRow::query()->create(['tpb_occupancy_import_id' => $import->id, 'time_slot_id' => $this->slot->id, 'room_id' => Room::factory()->create()->id]);
        $this->getDataset(['period' => '2030/2031-GANJIL'])->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
        $this->giveCells($this->program, '2030/2031-GANJIL', [[$this->slot, $this->room]]);
        $this->getDataset(['period' => '2030/2031-GANJIL'])->assertOk()->assertJsonPath('data.tpb_source.type', 'import');

        // Every class needs a lecturer.
        $course->update(['parallel_class_count' => 2]);
        $this->getDataset()->assertStatus(409);
        $course->update(['parallel_class_count' => 1]);
        $this->getDataset()->assertOk()->assertJsonPath('data.tpb_run_id', SchedulingRun::query()->whereNull('faculty_id')->value('id'));

        // Once handed in or published, the program cannot run the GA.
        $this->setSubmission($this->program, self::PERIOD, SubmissionStatus::SUBMITTED);
        $this->getDataset()->assertStatus(409);
        $this->setSubmission($this->program, self::PERIOD, SubmissionStatus::RETURNED);
        $this->getDataset()->assertOk();
        SchedulePublication::query()->create(['faculty_id' => $this->faculty->id, 'period' => self::PERIOD, 'published_by' => User::factory()->create()->id, 'published_at' => now(), 'checksum' => 'x']);
        $this->getDataset()->assertStatus(409);
    }

    public function test_desktop_reads_constraint_types_through_v1(): void
    {
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getJson('/api/v1/constraint-types')->assertOk()->assertJsonCount(8, 'data');
        $this->getJson("/api/v1/constraint-submissions/{$this->program->id}")->assertOk()->assertJsonPath('data.summary.id', $this->program->id);
    }
}
