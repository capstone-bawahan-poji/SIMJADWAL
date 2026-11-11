<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Enums\Scheduling\SubmissionStatus;
use App\Models\Account\ActivityLog;
use App\Models\Constraint\ConstraintType;
use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProgramSchedules;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use BuildsProgramSchedules, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private Faculty $otherFaculty;

    private StudyProgram $program;

    private StudyProgram $sibling;

    private StudyProgram $otherProgram;

    private User $admin;

    private Lecturer $lecturer;

    /** @var list<TimeSlot> */
    private array $slots;

    private Room $room;

    private Room $spare;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->otherFaculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->sibling = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->otherProgram = StudyProgram::factory()->create(['faculty_id' => $this->otherFaculty->id]);
        $this->admin = User::factory()->studyProgramAdmin($this->program)->create();
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 1, 'session' => $session]), [1, 2, 3, 4]);
        $this->room = Room::factory()->create(['faculty_id' => null, 'capacity' => 50]);
        $this->spare = Room::factory()->create(['faculty_id' => $this->faculty->id, 'capacity' => 50]);
        $this->course = $this->course($this->program, 3);
        // The program holds the shared room all morning, plus the faculty room in session 4.
        $this->giveCells($this->program, self::PERIOD, [
            ...array_map(fn (TimeSlot $slot) => [$slot, $this->room], $this->slots),
            [$this->slots[3], $this->spare],
        ]);

        Sanctum::actingAs($this->admin);
    }

    private function course(StudyProgram $program, int $semester): Course
    {
        return Course::factory()->create(['study_program_id' => $program->id, 'sks' => 3, 'semester' => $semester, 'class_capacity' => 40]);
    }

    private function makeRun(StudyProgram $program, array $attributes = []): SchedulingRun
    {
        return SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $program->faculty_id,
            'study_program_id' => $program->id,
            'period' => self::PERIOD,
            'parameter_snapshot' => [],
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

    public function test_activate_replaces_the_active_run_of_the_program_and_becomes_its_schedule(): void
    {
        $old = $this->makeRun($this->program, ['is_active' => true]);
        $siblingRun = $this->makeRun($this->sibling, ['is_active' => true]);
        $new = $this->makeRun($this->program);
        $this->gene($new, $this->course, 0, $this->room->id);

        $this->postJson("/api/v1/scheduling-runs/{$new->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.published_at', null);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertTrue($siblingRun->fresh()->is_active);
        $this->assertSame($new->id, ProgramScheduleSubmission::query()->where('study_program_id', $this->program->id)->value('scheduling_run_id'));
    }

    public function test_activate_rejects_unfinished_run_and_a_handed_in_schedule(): void
    {
        $run = $this->makeRun($this->program, ['status' => RunStatus::RUNNING]);
        $this->postJson("/api/v1/scheduling-runs/{$run->id}/activate")->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');

        $done = $this->makeRun($this->program);
        $this->setSubmission($this->program, self::PERIOD, SubmissionStatus::SUBMITTED);
        $this->postJson("/api/v1/scheduling-runs/{$done->id}/activate")->assertStatus(409);
        $this->assertFalse($done->fresh()->is_active);
    }

    public function test_activate_rejects_a_run_that_no_longer_fits(): void
    {
        // A cell the faculty took back.
        $outside = $this->makeRun($this->program);
        $this->gene($outside, $this->course, 0, $this->spare->id);
        $this->postJson("/api/v1/scheduling-runs/{$outside->id}/activate")->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');

        // A lecturer another program activated in the meantime.
        $sharedLecturer = $this->makeRun($this->sibling, ['is_active' => true]);
        $this->gene($sharedLecturer, $this->course($this->sibling, 3), 1, null);
        $clash = $this->makeRun($this->program);
        $this->gene($clash, $this->course, 1, $this->room->id);
        $this->postJson("/api/v1/scheduling-runs/{$clash->id}/activate")->assertStatus(409);
        $this->assertFalse($clash->fresh()->is_active);
    }

    public function test_only_the_program_activates_its_runs(): void
    {
        $run = $this->makeRun($this->sibling);
        $this->postJson("/api/v1/scheduling-runs/{$run->id}/activate")->assertForbidden();

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->postJson("/api/v1/scheduling-runs/{$run->id}/activate")->assertForbidden();
    }

    public function test_a_program_run_is_not_published_alone(): void
    {
        $active = $this->makeRun($this->program, ['is_active' => true]);
        $this->postJson("/api/v1/scheduling-runs/{$active->id}/publish")->assertForbidden();

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->postJson("/api/v1/scheduling-runs/{$active->id}/publish")->assertStatus(409);
        $this->assertNull($active->fresh()->published_at);
    }

    public function test_who_sees_an_unpublished_program_schedule(): void
    {
        $run = $this->programRun($this->program, self::PERIOD, [[
            'course_id' => $this->course->id, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id,
            'time_slot_id' => $this->slots[0]->id, 'room_id' => $this->room->id,
        ]]);

        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(1, 'data.schedule_details');

        $facultyAdmin = User::factory()->facultyAdmin($this->faculty)->create();
        Sanctum::actingAs($facultyAdmin);
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(0, 'data.schedule_details');
        $this->setSubmission($this->program, self::PERIOD, SubmissionStatus::SUBMITTED);
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(1, 'data.schedule_details');

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->sibling)->create());
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(0, 'data.schedule_details');

        Sanctum::actingAs(User::factory()->lecturer($this->lecturer)->create());
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(0, 'data.schedule_details');

        $run->update(['published_at' => now()]);
        $this->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data.schedule_details')
            ->assertJsonPath('data.schedule_details.0.scheduling_run_id', $run->id)
            ->assertJsonPath('data.runs.0.faculty.id', $this->faculty->id);
    }

    public function test_lecturers_see_every_faculty(): void
    {
        $mine = $this->makeRun($this->program, ['is_active' => true, 'published_at' => now()]);
        $this->gene($mine, $this->course, 0, null);
        $other = $this->makeRun($this->otherProgram, ['is_active' => true, 'published_at' => now()]);
        $this->gene($other, $this->course($this->otherProgram, 3), 1, null);

        Sanctum::actingAs(User::factory()->lecturer($this->lecturer)->create());
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(2, 'data.schedule_details');
        $this->getJson('/api/v1/schedules?faculty_id='.$this->otherFaculty->id)->assertOk()->assertJsonCount(1, 'data.schedule_details');
    }

    public function test_schedule_filters(): void
    {
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $other = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->gene($run, $this->course, 0, $this->room->id);
        $this->gene($run, $this->course($this->program, 5), 1, null, $other);

        $this->getJson('/api/v1/schedules?semester=5')->assertJsonCount(1, 'data.schedule_details');
        $this->getJson("/api/v1/schedules?lecturer_id={$other->id}")->assertJsonCount(1, 'data.schedule_details');
        $this->getJson("/api/v1/schedules?room_id={$this->room->id}")->assertJsonCount(1, 'data.schedule_details');
        $this->getJson('/api/v1/schedules?period=2026/2027-GENAP')->assertJsonCount(0, 'data.schedule_details');
    }

    public function test_override_moves_a_meeting_to_a_free_cell_and_clears_its_violations(): void
    {
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $detail = $this->gene($run, $this->course, 0, $this->room->id);
        $detail->violationLogs()->create(['scheduling_run_id' => $run->id, 'constraint_type_id' => ConstraintType::query()->value('id'), 'penalty' => 1]);

        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[3]->id, 'room_id' => $this->spare->id, 'reason' => 'Dosen berhalangan'])
            ->assertOk()
            ->assertJsonPath('data.time_slot.id', $this->slots[3]->id)
            ->assertJsonPath('data.room.id', $this->spare->id)
            ->assertJsonPath('data.is_overridden', true);

        $this->assertSame($this->admin->id, $detail->fresh()->overridden_by);
        $this->assertSame(0, $detail->violationLogs()->count());
        $log = ActivityLog::query()->where('event', 'schedule_detail.overridden')->latest('id')->firstOrFail();
        $this->assertSame('Dosen berhalangan', data_get($log->properties, 'reason'));

        // A room the program does not hold in that slot, or no room at all.
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[0]->id, 'room_id' => $this->spare->id])
            ->assertUnprocessable()->assertJsonValidationErrors('room_id');
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[0]->id, 'room_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('room_id');
    }

    public function test_override_rejects_lecturer_clash_in_another_program(): void
    {
        $other = $this->makeRun($this->otherProgram, ['is_active' => true]);
        $this->gene($other, $this->course($this->otherProgram, 3), 2, null);
        $detail = $this->gene($this->makeRun($this->program, ['is_active' => true]), $this->course, 0, $this->room->id);

        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[2]->id, 'room_id' => $this->room->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
    }

    public function test_override_rejects_taken_cell_cohort_clash_and_small_room(): void
    {
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $otherLecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->gene($run, $this->course($this->program, 3), 1, $this->room->id, $otherLecturer);
        $detail = $this->gene($run, $this->course, 0, $this->room->id);

        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->room->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['room_id', 'time_slot_id']);

        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 10]);
        $this->giveCells($this->program, self::PERIOD, [[$this->slots[2], $small]]);
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[2]->id, 'room_id' => $small->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');
    }

    public function test_override_only_on_the_active_draft_of_own_program(): void
    {
        $draft = $this->gene($this->makeRun($this->program), $this->course, 0, $this->room->id);
        $this->patchJson("/api/v1/schedule-details/{$draft->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->room->id])
            ->assertStatus(409);

        $foreign = $this->gene($this->makeRun($this->sibling, ['is_active' => true]), $this->course($this->sibling, 3), 0, null);
        $this->patchJson("/api/v1/schedule-details/{$foreign->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->room->id])
            ->assertForbidden();

        $mine = $this->gene($this->makeRun($this->program, ['is_active' => true]), $this->course, 0, $this->room->id);
        $this->setSubmission($this->program, self::PERIOD, SubmissionStatus::SUBMITTED);
        $this->patchJson("/api/v1/schedule-details/{$mine->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->room->id])
            ->assertStatus(409);

        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->patchJson("/api/v1/schedule-details/{$mine->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->room->id])
            ->assertForbidden();
    }

    public function test_swap_exchanges_the_cells_of_two_meetings(): void
    {
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $otherLecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $a = $this->gene($run, $this->course, 0, $this->room->id);
        $b = $this->gene($run, $this->course($this->program, 5), 3, $this->spare->id, $otherLecturer);

        $this->postJson('/api/v1/schedule-details/swap', ['a' => $a->id, 'b' => $b->id])
            ->assertOk()
            ->assertJsonPath('data.0.time_slot.id', $this->slots[3]->id)
            ->assertJsonPath('data.0.room.id', $this->spare->id)
            ->assertJsonPath('data.1.time_slot.id', $this->slots[0]->id)
            ->assertJsonPath('data.1.room.id', $this->room->id);
        $this->assertTrue($a->fresh()->is_overridden);
        $this->assertDatabaseHas('activity_logs', ['event' => 'schedule_detail.swapped']);
    }

    public function test_swap_is_refused_when_it_breaks_a_hard_constraint(): void
    {
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $a = $this->gene($run, $this->course, 0, $this->room->id);
        $b = $this->gene($run, $this->course($this->program, 5), 3, $this->spare->id, Lecturer::factory()->create(['study_program_id' => $this->program->id]));
        // a's lecturer already teaches another class in b's slot.
        $this->gene($run, $this->course($this->program, 7), 3, $this->room->id);

        $this->postJson('/api/v1/schedule-details/swap', ['a' => $a->id, 'b' => $b->id])
            ->assertUnprocessable()->assertJsonValidationErrors('a');
        $this->assertSame($this->slots[0]->id, $a->fresh()->time_slot_id);

        $foreign = $this->gene($this->makeRun($this->program, ['is_active' => false]), $this->course, 1, $this->room->id);
        $this->postJson('/api/v1/schedule-details/swap', ['a' => $a->id, 'b' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('b');
    }

    public function test_options_list_the_program_cells_and_swap_candidates(): void
    {
        $other = $this->makeRun($this->otherProgram, ['is_active' => true]);
        $this->gene($other, $this->course($this->otherProgram, 3), 1, null);
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $detail = $this->gene($run, $this->course, 0, $this->room->id);
        $partner = $this->gene($run, $this->course($this->program, 5), 2, $this->room->id, Lecturer::factory()->create(['study_program_id' => $this->program->id]));
        $notMine = Room::factory()->create(['faculty_id' => null, 'capacity' => 99]);

        $response = $this->getJson("/api/v1/schedule-details/{$detail->id}/options")
            ->assertOk()
            ->assertJsonPath('data.current.room_id', $this->room->id)
            ->assertJsonPath('data.swap_candidates.0.schedule_detail_id', $partner->id);
        $slots = collect($response->json('data.time_slots'))->keyBy('id');
        $roomIds = fn (int $i) => array_column($slots[$this->slots[$i]->id]['rooms'], 'id');

        $this->assertTrue($slots[$this->slots[0]->id]['available']);
        $this->assertSame([$this->room->id], $roomIds(0));
        $this->assertNotContains($notMine->id, $roomIds(3));
        $this->assertEqualsCanonicalizing([$this->room->id, $this->spare->id], $roomIds(3));

        $this->assertFalse($slots[$this->slots[1]->id]['available']);
        $this->assertNotEmpty($slots[$this->slots[1]->id]['reasons']);

        $this->assertTrue($slots[$this->slots[2]->id]['available']);
        $this->assertSame([], $roomIds(2));
    }

    public function test_room_problem_filters_and_class_capacity(): void
    {
        $run = $this->makeRun($this->program, ['is_active' => true]);
        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 10]);
        $this->gene($run, $this->course, 0, null);
        $this->gene($run, $this->course($this->program, 5), 1, $small->id, Lecturer::factory()->create(['study_program_id' => $this->program->id]));
        $this->gene($run, $this->course($this->program, 7), 2, $this->room->id, Lecturer::factory()->create(['study_program_id' => $this->program->id]));

        $this->getJson('/api/v1/schedules?unassigned=1')->assertJsonCount(1, 'data.schedule_details');
        $this->getJson('/api/v1/schedules?over_capacity=1')
            ->assertJsonCount(1, 'data.schedule_details')
            ->assertJsonPath('data.schedule_details.0.room.id', $small->id)
            ->assertJsonPath('data.schedule_details.0.course.class_capacity', 40);
        $this->getJson('/api/v1/schedules')->assertJsonCount(3, 'data.schedule_details');
    }

    public function test_runs_filter_by_active_flag(): void
    {
        $active = $this->makeRun($this->program, ['is_active' => true]);
        $this->makeRun($this->program);

        $this->getJson('/api/v1/scheduling-runs?is_active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
        $this->getJson('/api/v1/scheduling-runs?is_active=0')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/scheduling-runs')->assertJsonCount(2, 'data');
    }
}
