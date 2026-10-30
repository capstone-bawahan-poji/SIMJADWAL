<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\Course;
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
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private Faculty $otherFaculty;

    private StudyProgram $program;

    private StudyProgram $otherProgram;

    private User $admin;

    private Lecturer $lecturer;

    /** @var list<TimeSlot> */
    private array $slots;

    private Room $sharedRoom;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->otherFaculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id]);
        $this->otherProgram = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->otherFaculty->id]);
        $this->admin = User::factory()->facultyAdmin($this->faculty)->create();
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 1, 'session' => $session]), [1, 2, 3, 4]);
        $this->sharedRoom = Room::factory()->create(['faculty_id' => null, 'capacity' => 50]);
        $this->course = $this->course($this->program, 3);

        Sanctum::actingAs($this->admin);
    }

    private function course(StudyProgram $program, int $semester): Course
    {
        return Course::factory()->create(['study_program_id' => $program->id, 'sks' => 3, 'semester' => $semester, 'class_capacity' => 40]);
    }

    private function makeRun(Faculty $faculty, array $attributes = []): SchedulingRun
    {
        return SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $faculty->id,
            'period' => self::PERIOD,
            'parameter_snapshot' => ['parallel_class_limit' => 5],
            'status' => RunStatus::DONE,
            'triggered_by' => $this->admin->id,
            ...$attributes,
        ]);
    }

    private function gene(SchedulingRun $run, Course $course, int $slot, ?int $roomId, ?Lecturer $lecturer = null, int $classNumber = 1): ScheduleDetail
    {
        return ScheduleDetail::query()->create([
            'scheduling_run_id' => $run->id,
            'course_id' => $course->id,
            'class_number' => $classNumber,
            'meeting_number' => 1,
            'lecturer_id' => ($lecturer ?? $this->lecturer)->id,
            'time_slot_id' => $this->slots[$slot]->id,
            'room_id' => $roomId,
        ]);
    }

    public function test_activate_replaces_the_active_run_of_the_faculty_and_period(): void
    {
        $old = $this->makeRun($this->faculty, ['is_active' => true, 'published_at' => now()]);
        $new = $this->makeRun($this->faculty);

        $this->postJson("/api/v1/scheduling-runs/{$new->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.published_at', null);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertNotNull($old->fresh()->published_at);
        $this->assertSame(1, SchedulingRun::query()->active()->where('faculty_id', $this->faculty->id)->count());
    }

    public function test_activate_rejects_unfinished_run(): void
    {
        $run = $this->makeRun($this->faculty, ['status' => RunStatus::RUNNING]);

        $this->postJson("/api/v1/scheduling-runs/{$run->id}/activate")
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATE');
    }

    public function test_activate_rejects_room_clash_with_another_faculty(): void
    {
        $other = $this->makeRun($this->otherFaculty, ['is_active' => true]);
        $this->gene($other, $this->course($this->otherProgram, 3), 0, $this->sharedRoom->id, Lecturer::factory()->create(['study_program_id' => $this->otherProgram->id]));
        $run = $this->makeRun($this->faculty);
        $this->gene($run, $this->course, 0, $this->sharedRoom->id);

        $this->postJson("/api/v1/scheduling-runs/{$run->id}/activate")
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATE');
        $this->assertFalse($run->fresh()->is_active);
    }

    public function test_admin_of_another_faculty_cannot_activate(): void
    {
        $run = $this->makeRun($this->otherFaculty);

        $this->postJson("/api/v1/scheduling-runs/{$run->id}/activate")->assertForbidden();
    }

    public function test_publish_requires_active_run(): void
    {
        $draft = $this->makeRun($this->faculty);
        $this->postJson("/api/v1/scheduling-runs/{$draft->id}/publish")->assertStatus(409);

        $active = $this->makeRun($this->faculty, ['is_active' => true]);
        $this->postJson("/api/v1/scheduling-runs/{$active->id}/publish")->assertOk();
        $this->assertSame($this->admin->id, $active->fresh()->published_by);
        $this->assertNotNull($active->fresh()->published_at);
    }

    public function test_unpublished_active_run_is_hidden_from_students(): void
    {
        $run = $this->makeRun($this->faculty, ['is_active' => true]);
        $this->gene($run, $this->course, 0, $this->sharedRoom->id);

        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(1, 'data.schedule_details');

        Sanctum::actingAs(User::factory()->student($this->program)->create());
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(0, 'data.schedule_details');

        $run->update(['published_at' => now()]);
        $this->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data.schedule_details')
            ->assertJsonPath('data.schedule_details.0.scheduling_run_id', $run->id)
            ->assertJsonPath('data.runs.0.faculty.id', $this->faculty->id);
    }

    public function test_students_see_their_faculty_and_lecturers_see_every_faculty(): void
    {
        $mine = $this->makeRun($this->faculty, ['is_active' => true, 'published_at' => now()]);
        $this->gene($mine, $this->course, 0, null);
        $other = $this->makeRun($this->otherFaculty, ['is_active' => true, 'published_at' => now()]);
        $this->gene($other, $this->course($this->otherProgram, 3), 1, null);

        Sanctum::actingAs(User::factory()->student($this->program)->create());
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(1, 'data.schedule_details');

        Sanctum::actingAs(User::factory()->lecturer($this->lecturer)->create());
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(2, 'data.schedule_details');
        $this->getJson('/api/v1/schedules?faculty_id='.$this->otherFaculty->id)->assertOk()->assertJsonCount(1, 'data.schedule_details');
    }

    public function test_schedule_filters(): void
    {
        $run = $this->makeRun($this->faculty, ['is_active' => true]);
        $other = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->gene($run, $this->course, 0, $this->sharedRoom->id);
        $this->gene($run, $this->course($this->program, 5), 1, null, $other);

        $this->getJson('/api/v1/schedules?semester=5')->assertJsonCount(1, 'data.schedule_details');
        $this->getJson("/api/v1/schedules?lecturer_id={$other->id}")->assertJsonCount(1, 'data.schedule_details');
        $this->getJson("/api/v1/schedules?room_id={$this->sharedRoom->id}")->assertJsonCount(1, 'data.schedule_details');
        $this->getJson('/api/v1/schedules?period=2026/2027-GENAP')->assertJsonCount(0, 'data.schedule_details');
    }

    public function test_override_moves_gene_and_clears_its_violations(): void
    {
        $run = $this->makeRun($this->faculty, ['is_active' => true]);
        $detail = $this->gene($run, $this->course, 0, null);
        $detail->violationLogs()->create(['scheduling_run_id' => $run->id, 'constraint_type_id' => ConstraintType::query()->value('id'), 'penalty' => 1]);

        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[2]->id, 'room_id' => $this->sharedRoom->id])
            ->assertOk()
            ->assertJsonPath('data.time_slot.id', $this->slots[2]->id)
            ->assertJsonPath('data.room.id', $this->sharedRoom->id)
            ->assertJsonPath('data.is_overridden', true);

        $this->assertSame($this->admin->id, $detail->fresh()->overridden_by);
        $this->assertSame(0, $detail->violationLogs()->count());
    }

    public function test_override_rejects_lecturer_clash_in_another_faculty(): void
    {
        $other = $this->makeRun($this->otherFaculty, ['is_active' => true]);
        $this->gene($other, $this->course($this->otherProgram, 3), 2, null);
        $detail = $this->gene($this->makeRun($this->faculty, ['is_active' => true]), $this->course, 0, null);

        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[2]->id, 'room_id' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
    }

    public function test_override_rejects_room_clash_cohort_clash_and_small_room(): void
    {
        $run = $this->makeRun($this->faculty, ['is_active' => true]);
        $otherLecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->gene($run, $this->course($this->program, 3), 1, $this->sharedRoom->id, $otherLecturer);
        $detail = $this->gene($run, $this->course, 0, null);

        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->sharedRoom->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['room_id', 'time_slot_id']);

        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 10]);
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[3]->id, 'room_id' => $small->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');
    }

    public function test_override_rejects_inactive_run_and_other_faculty(): void
    {
        $draft = $this->gene($this->makeRun($this->faculty), $this->course, 0, null);
        $this->patchJson("/api/v1/schedule-details/{$draft->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => null])
            ->assertStatus(409);

        $foreign = $this->gene($this->makeRun($this->otherFaculty, ['is_active' => true]), $this->course($this->otherProgram, 3), 0, null);
        $this->patchJson("/api/v1/schedule-details/{$foreign->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => null])
            ->assertForbidden();
    }

    public function test_options_list_free_slots_and_rooms_with_reasons(): void
    {
        $other = $this->makeRun($this->otherFaculty, ['is_active' => true]);
        $this->gene($other, $this->course($this->otherProgram, 3), 1, null);
        $this->gene($other, $this->course($this->otherProgram, 3), 2, $this->sharedRoom->id, Lecturer::factory()->create(['study_program_id' => $this->otherProgram->id]));
        $detail = $this->gene($this->makeRun($this->faculty, ['is_active' => true]), $this->course, 0, $this->sharedRoom->id);
        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 10]);
        $foreign = Room::factory()->create(['faculty_id' => $this->otherFaculty->id, 'capacity' => 99]);

        $slots = collect($this->getJson("/api/v1/schedule-details/{$detail->id}/options")
            ->assertOk()
            ->assertJsonPath('data.current.room_id', $this->sharedRoom->id)
            ->json('data.time_slots'))->keyBy('id');

        $roomIds = fn (int $i) => array_column($slots[$this->slots[$i]->id]['rooms'], 'id');

        $this->assertTrue($slots[$this->slots[0]->id]['available']);
        $this->assertContains($this->sharedRoom->id, $roomIds(0));
        $this->assertNotContains($small->id, $roomIds(0));
        $this->assertNotContains($foreign->id, $roomIds(0));

        $this->assertFalse($slots[$this->slots[1]->id]['available']);
        $this->assertNotEmpty($slots[$this->slots[1]->id]['reasons']);
        $this->assertSame([], $slots[$this->slots[1]->id]['rooms']);

        $this->assertTrue($slots[$this->slots[2]->id]['available']);
        $this->assertNotContains($this->sharedRoom->id, $roomIds(2));
    }

    public function test_options_reject_inactive_run_and_other_faculty(): void
    {
        $draft = $this->gene($this->makeRun($this->faculty), $this->course, 0, null);
        $this->getJson("/api/v1/schedule-details/{$draft->id}/options")->assertStatus(409);

        $foreign = $this->gene($this->makeRun($this->otherFaculty, ['is_active' => true]), $this->course($this->otherProgram, 3), 0, null);
        $this->getJson("/api/v1/schedule-details/{$foreign->id}/options")->assertForbidden();
    }

    public function test_room_problem_filters_and_class_capacity(): void
    {
        $run = $this->makeRun($this->faculty, ['is_active' => true]);
        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 10]);
        $other = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->gene($run, $this->course, 0, null);
        $this->gene($run, $this->course($this->program, 5), 1, $small->id, $other);
        $this->gene($run, $this->course($this->program, 7), 2, $this->sharedRoom->id, Lecturer::factory()->create(['study_program_id' => $this->program->id]));

        $this->getJson('/api/v1/schedules?unassigned=1')->assertJsonCount(1, 'data.schedule_details');
        $this->getJson('/api/v1/schedules?over_capacity=1')
            ->assertJsonCount(1, 'data.schedule_details')
            ->assertJsonPath('data.schedule_details.0.room.id', $small->id)
            ->assertJsonPath('data.schedule_details.0.course.class_capacity', 40);
        $this->getJson('/api/v1/schedules')->assertJsonCount(3, 'data.schedule_details');
    }

    public function test_runs_filter_by_active_flag(): void
    {
        $active = $this->makeRun($this->faculty, ['is_active' => true]);
        $this->makeRun($this->faculty);

        $this->getJson('/api/v1/scheduling-runs?is_active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
        $this->getJson('/api/v1/scheduling-runs?is_active=0')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/scheduling-runs')->assertJsonCount(2, 'data');
    }
}