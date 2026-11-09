<?php

namespace Tests\Feature\Scheduling;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\Scheduling\TpbBlockService;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTpbRun;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * TPB is scheduled first by its own GA run. The active TPB run then blocks slots of programs
 * (regular groups, hard), lecturers and rooms for every faculty, and asks the faculty GA to keep
 * one class of each course out of the retake slots (soft).
 */
class TpbSchedulingTest extends TestCase
{
    use CreatesTpbRun, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    /** @var list<TimeSlot> */
    private array $slots;

    private Faculty $faculty;

    private StudyProgram $informatics;

    private Lecturer $lecturer;

    private Room $room;

    private TpbOffering $math;

    private TpbGroup $maba;

    private TpbGroup $retake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 1, 'session' => $session]), [1, 2, 3, 4]);
        $this->faculty = Faculty::factory()->create();
        $this->informatics = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id]);
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $this->informatics->id]);
        $this->room = Room::factory()->shared()->create(['capacity' => 60]);

        $this->math = TpbOffering::factory()->create([
            'course_id' => Course::factory()->tpb()->create(['sks' => 3])->id,
            'period' => self::PERIOD, 'parallel_class_count' => 3, 'class_capacity' => 40,
        ]);
        $this->maba = $this->group('G1', TpbCohort::MABA, [$this->informatics->id => 1], [1 => $this->lecturer, 2 => Lecturer::factory()->create()]);
        $this->retake = $this->group('G2', TpbCohort::MENGULANG, [$this->informatics->id => null], [3 => Lecturer::factory()->create()]);
    }

    public function test_active_tpb_run_blocks_programs_retake_lecturers_and_rooms(): void
    {
        $course = $this->math->course_id;
        $this->activeTpbRun(self::PERIOD, [
            ['course_id' => $course, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => $this->room->id],
            ['course_id' => $course, 'class_number' => 2, 'lecturer_id' => $this->classOf(2)->lecturer_id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => null],
            ['course_id' => $course, 'class_number' => 3, 'lecturer_id' => $this->classOf(3)->lecturer_id, 'time_slot_id' => $this->slots[2]->id, 'room_id' => null],
        ]);

        $blocks = app(TpbBlockService::class)->blocks(self::PERIOD);

        $this->assertSame([$this->informatics->id => [1 => [$this->slots[0]->id]]], $blocks['programs']);
        $this->assertSame([$this->informatics->id => [$this->slots[2]->id]], $blocks['retake']);
        $this->assertSame([$this->slots[0]->id], $blocks['lecturers'][$this->lecturer->id]);
        $this->assertSame([$this->room->id => [$this->slots[0]->id]], $blocks['rooms']);
        $this->assertSame([], app(TpbBlockService::class)->blocks('2026/2027-GENAP')['programs']);
    }

    public function test_faculty_dataset_carries_the_blocks_and_tpb_rooms(): void
    {
        $tpb = $this->activeTpbRun(self::PERIOD, [
            ['course_id' => $this->math->course_id, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => $this->room->id],
        ]);
        $course = Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 1]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'lecturer_id' => $this->lecturer->id]);
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.tpb_run_id', $tpb->id)
            ->assertJsonPath('data.tpb_blocks.study_programs.0', ['study_program_id' => $this->informatics->id, 'semester' => 1, 'time_slot_ids' => [$this->slots[0]->id]])
            ->assertJsonPath('data.tpb_blocks.lecturers.0.lecturer_id', $this->lecturer->id)
            ->assertJsonPath('data.occupied_rooms.0', ['time_slot_id' => $this->slots[0]->id, 'room_id' => $this->room->id]);
    }

    public function test_tpb_dataset_needs_every_class_to_have_a_lecturer(): void
    {
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->classOf(2)->update(['lecturer_id' => null]);

        $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))->assertStatus(409);

        $this->classOf(2)->update(['lecturer_id' => $this->lecturer->id]);
        $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.scope', 'tpb')
            ->assertJsonCount(3, 'data.classes')
            ->assertJsonPath('data.tpb_groups.0.cohort', 'maba')
            ->assertJsonPath('data.tpb_groups.0.study_programs.0', ['id' => $this->informatics->id, 'target_semester' => 1])
            ->assertJsonPath('data.courses.0.class_capacity', 40);
    }

    public function test_tpb_upload_keeps_groups_together_and_counts_program_clashes(): void
    {
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $other = $this->secondCourseGroup();

        // Classes 1 and 2 of G1 in different slots: refused.
        $split = $this->tpbPayload([0, 1, 2, 3]);
        $this->postJson('/api/v1/scheduling-runs', $split)->assertUnprocessable()->assertJsonValidationErrors('schedule_details.1.time_slot_id');

        // G1 and the other course's group share program + semester 1 in slot 0: one HC3.
        $clash = $this->tpbPayload([0, 0, 2, 0]);
        $this->postJson('/api/v1/scheduling-runs', $clash)->assertUnprocessable()->assertJsonValidationErrors('violations.HC3');

        $clash['client_run_id'] = (string) Str::uuid();
        $clash['violations'] = [['detail_index' => 3, 'constraint_code' => 'HC3', 'penalty' => 10, 'message' => 'bentrok']];
        $this->postJson('/api/v1/scheduling-runs', $clash)->assertCreated()->assertJsonPath('data.scope', 'tpb')->assertJsonPath('data.faculty', null);

        $this->postJson('/api/v1/scheduling-runs', $this->tpbPayload([0, 0, 2, 1]))->assertCreated();
        $this->assertNotNull($other);
    }

    public function test_a_newer_published_tpb_run_only_flags_faculty_runs_as_stale(): void
    {
        $first = $this->activeTpbRun(self::PERIOD);
        $facultyRun = SchedulingRun::query()->create([
            'client_run_id' => (string) Str::uuid(), 'batch_id' => (string) Str::uuid(), 'faculty_id' => $this->faculty->id,
            'period' => self::PERIOD, 'parameter_snapshot' => ['parallel_class_limit' => 5], 'status' => 'done',
            'triggered_by' => User::factory()->create()->id, 'tpb_run_id' => $first->id,
        ]);
        $second = $first->replicate()->fill(['client_run_id' => (string) Str::uuid(), 'is_active' => false, 'published_at' => null]);
        $second->save();

        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->postJson("/api/v1/scheduling-runs/{$second->id}/activate")->assertOk();
        $this->postJson("/api/v1/scheduling-runs/{$second->id}/publish")->assertOk();
        $this->assertFalse($first->fresh()->is_active);

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->getJson("/api/v1/scheduling-runs/{$facultyRun->id}")->assertJsonPath('data.is_stale', true);
        $this->postJson("/api/v1/scheduling-runs/{$facultyRun->id}/activate")->assertOk();
    }

    public function test_faculties_wait_for_the_tpb_schedule_to_be_published(): void
    {
        $this->activeTpbRun(self::PERIOD, [], published: false);
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))->assertStatus(409);
        $this->assertSame([], app(TpbBlockService::class)->blocks(self::PERIOD)['programs']);
    }

    public function test_tpb_dataset_has_no_lecturer_preferences(): void
    {
        $slot = $this->slots[0]->id;
        \App\Models\Constraint\LecturerPreference::query()->create([
            'lecturer_id' => $this->lecturer->id,
            'constraint_type_id' => \App\Models\Constraint\ConstraintType::query()->where('code', 'SC_INGIN')->value('id'),
            'time_slot_id' => $slot,
        ]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());

        $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))->assertOk()->assertJsonPath('data.lecturers.0.preferences', []);
    }

    public function test_revision_freezes_other_groups_and_needs_chosen_groups_once_faculties_are_scheduled(): void
    {
        $course = $this->math->course_id;
        $this->activeTpbRun(self::PERIOD, [
            ['course_id' => $course, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => $this->room->id],
            ['course_id' => $course, 'class_number' => 2, 'lecturer_id' => $this->classOf(2)->lecturer_id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => null],
            ['course_id' => $course, 'class_number' => 3, 'lecturer_id' => $this->classOf(3)->lecturer_id, 'time_slot_id' => $this->slots[2]->id, 'room_id' => null],
        ]);
        $facultyCourse = Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 1]);
        $facultyRun = SchedulingRun::query()->create([
            'client_run_id' => (string) Str::uuid(), 'batch_id' => (string) Str::uuid(), 'faculty_id' => $this->faculty->id,
            'period' => self::PERIOD, 'parameter_snapshot' => ['parallel_class_limit' => 5], 'status' => 'done', 'is_active' => true,
            'triggered_by' => User::factory()->create()->id,
        ]);
        ScheduleDetail::query()->create(['scheduling_run_id' => $facultyRun->id, 'course_id' => $facultyCourse->id, 'class_number' => 1, 'meeting_number' => 1,
            'lecturer_id' => $this->classOf(3)->lecturer_id, 'time_slot_id' => $this->slots[3]->id, 'room_id' => $this->room->id]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $url = '/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD);

        $this->getJson($url)->assertOk()->assertJsonPath('data.revision.required', true)->assertJsonPath('data.tpb_blocks.study_programs', []);

        // Revise only the retake group (class 3): classes 1-2 keep slot and room, faculty slots are blocked.
        $this->getJson($url.'&revise_group_ids[]='.$this->retake->id)
            ->assertOk()
            ->assertJsonPath('data.revision.revise_group_ids', [$this->retake->id])
            ->assertJsonPath('data.classes.0.fixed_time_slot_ids', [$this->slots[0]->id])
            ->assertJsonPath('data.classes.0.fixed_room_ids', [$this->room->id])
            ->assertJsonMissingPath('data.classes.2.fixed_time_slot_ids')
            ->assertJsonPath('data.tpb_blocks.study_programs.0', ['study_program_id' => $this->informatics->id, 'semester' => 1, 'time_slot_ids' => [$this->slots[3]->id]])
            ->assertJsonPath('data.occupied_rooms.0', ['time_slot_id' => $this->slots[3]->id, 'room_id' => $this->room->id]);

        // Upload: refused without chosen groups, a moved frozen group and a faculty-held slot are errors.
        $base = $this->tpbPayload([0, 0, 2]);
        $this->postJson('/api/v1/scheduling-runs', $base)->assertUnprocessable()->assertJsonValidationErrors('revise_group_ids');
        $revise = fn (array $slots) => [...$this->tpbRevisionPayload($slots), 'revise_group_ids' => [$this->retake->id]];
        $this->postJson('/api/v1/scheduling-runs', $revise([1, 1, 2]))->assertUnprocessable()->assertJsonValidationErrors('schedule_details.0.time_slot_id');
        $this->postJson('/api/v1/scheduling-runs', $revise([0, 0, 3]))->assertUnprocessable()->assertJsonValidationErrors('schedule_details.2.time_slot_id');
        $this->postJson('/api/v1/scheduling-runs', $revise([0, 0, 1]))->assertCreated();
    }

    public function test_tpb_runs_are_listed_for_tpb_admin_and_never_overridden(): void
    {
        $run = $this->activeTpbRun(self::PERIOD, [
            ['course_id' => $this->math->course_id, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => null],
        ]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());

        $this->getJson('/api/v1/scheduling-runs')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $run->id);
        $detail = ScheduleDetail::query()->where('scheduling_run_id', $run->id)->sole();
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => null])->assertForbidden();
    }

    public function test_tpb_schedule_shows_its_group_to_every_program(): void
    {
        $this->activeTpbRun(self::PERIOD, [
            ['course_id' => $this->math->course_id, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id, 'time_slot_id' => $this->slots[0]->id, 'room_id' => null],
        ]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->informatics)->create());

        $this->getJson('/api/v1/schedules?'.http_build_query(['period' => self::PERIOD, 'study_program_id' => $this->informatics->id, 'semester' => 1]))
            ->assertOk()
            ->assertJsonPath('data.runs.0.scope', 'tpb')
            ->assertJsonCount(1, 'data.schedule_details')
            ->assertJsonPath('data.schedule_details.0.tpb_group.code', 'G1')
            ->assertJsonPath('data.schedule_details.0.course.class_capacity', 40);
        $this->getJson('/api/v1/schedules?'.http_build_query(['period' => self::PERIOD, 'study_program_id' => $this->informatics->id, 'semester' => 3]))
            ->assertOk()
            ->assertJsonCount(0, 'data.schedule_details');
    }

    /**
     * @param  array<int, int|null>  $programs
     * @param  array<int, Lecturer>  $classes  class_number => lecturer
     */
    private function group(string $code, TpbCohort $cohort, array $programs, array $classes, ?TpbOffering $offering = null): TpbGroup
    {
        $group = TpbGroup::factory()->create(['tpb_offering_id' => ($offering ?? $this->math)->id, 'code' => $code, 'cohort' => $cohort]);
        $group->studyPrograms()->sync(collect($programs)->map(fn (?int $semester) => ['target_semester' => $semester])->all());
        foreach ($classes as $number => $lecturer) {
            CourseLecturer::factory()->forTpbGroup($group)->create(['class_number' => $number, 'lecturer_id' => $lecturer->id]);
        }

        return $group;
    }

    private function classOf(int $number): CourseLecturer
    {
        return CourseLecturer::query()->where('tpb_offering_id', $this->math->id)->where('class_number', $number)->sole();
    }

    private function secondCourseGroup(): TpbGroup
    {
        $physics = TpbOffering::factory()->create([
            'course_id' => Course::factory()->tpb()->create(['sks' => 2])->id,
            'period' => self::PERIOD, 'parallel_class_count' => 1, 'class_capacity' => 40,
        ]);

        return $this->group('G1', TpbCohort::CAMPURAN, [$this->informatics->id => 1], [1 => Lecturer::factory()->create()], $physics);
    }

    /**
     * Same as tpbPayload() but built from the revision dataset (fingerprint included).
     *
     * @param  list<int>  $slots
     * @return array<string, mixed>
     */
    private function tpbRevisionPayload(array $slots): array
    {
        $dataset = $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD).'&revise_group_ids[]='.$this->retake->id)->assertOk()->json('data');
        $payload = $this->tpbPayload($slots);
        $payload['dataset_fingerprint'] = $dataset['fingerprint'];

        return $payload;
    }

    /**
     * Genes in dataset order (math classes 1-3, then the second course's class 1) with slot indexes.
     *
     * @param  list<int>  $slots
     * @return array<string, mixed>
     */
    private function tpbPayload(array $slots): array
    {
        $dataset = $this->getJson('/api/v1/scheduling/dataset?period='.urlencode(self::PERIOD))->assertOk()->json('data');
        $details = collect($dataset['classes'])->values()->map(fn (array $class, int $i) => [
            'course_id' => $class['course_id'],
            'class_number' => $class['class_number'],
            'meeting_number' => 1,
            'lecturer_id' => $class['lecturer_id'],
            'time_slot_id' => $this->slots[$slots[$i]]->id,
            'room_id' => null,
        ])->all();

        return [
            'client_run_id' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'period' => self::PERIOD,
            'dataset_fingerprint' => $dataset['fingerprint'],
            'preset_id' => null,
            'parameter_snapshot' => ['parallel_class_limit' => 5, 'weights' => ['HC3' => 10]],
            'best_fitness' => 500,
            'max_fitness' => 500,
            'best_generation' => 1,
            'final_generation' => 2,
            'fitness_history' => [500],
            'started_at' => '2026-10-19T10:00:00+08:00',
            'finished_at' => '2026-10-19T10:00:01+08:00',
            'schedule_details' => $details,
            'violations' => [],
        ];
    }
}
