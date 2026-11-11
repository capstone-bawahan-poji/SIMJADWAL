<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\SubmissionStatus;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulePublication;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProgramSchedules;
use Tests\Concerns\CreatesTpbRun;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * POS 040/IT10.1/OT.07/2026 steps 8-10: a program hands its schedule to the faculty, the faculty
 * can return it with a note, then publishes the whole faculty. A publication is final.
 */
class ProgramScheduleFlowTest extends TestCase
{
    use BuildsProgramSchedules, CreatesTpbRun, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $informatics;

    private StudyProgram $systems;

    private User $facultyAdmin;

    private User $informaticsAdmin;

    private User $systemsAdmin;

    /** @var array<int, SchedulingRun> program id => active run */
    private array $runs = [];

    /** @var list<TimeSlot> */
    private array $slots;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);
        $this->activeTpbRun(self::PERIOD);
        $this->faculty = Faculty::factory()->create();
        $this->informatics = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'IF']);
        $this->systems = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'SI']);
        // A program without courses this period does not hold the publication up.
        StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'ZZ']);
        $this->facultyAdmin = User::factory()->facultyAdmin($this->faculty)->create();
        $this->informaticsAdmin = User::factory()->studyProgramAdmin($this->informatics)->create();
        $this->systemsAdmin = User::factory()->studyProgramAdmin($this->systems)->create();
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 2, 'session' => $session]), [1, 2]);
        $this->room = Room::factory()->create(['faculty_id' => $this->faculty->id, 'capacity' => 60]);

        foreach ([$this->informatics, $this->systems] as $i => $program) {
            $course = Course::factory()->create(['study_program_id' => $program->id, 'semester' => 3, 'sks' => 3, 'class_capacity' => 40]);
            $lecturer = Lecturer::factory()->create(['study_program_id' => $program->id]);
            CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id]);
            $this->giveCells($program, self::PERIOD, [[$this->slots[$i], $this->room]]);
            $this->runs[$program->id] = $this->programRun($program, self::PERIOD, [[
                'course_id' => $course->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id,
                'time_slot_id' => $this->slots[$i]->id, 'room_id' => $this->room->id,
            ]]);
        }
    }

    private function step(StudyProgram $program, string $step, array $body = []): TestResponse
    {
        return $this->postJson("/api/v1/study-programs/{$program->id}/schedule-submission/{$step}?period=".urlencode(self::PERIOD), $body);
    }

    private function publication(string $method = 'post', array $body = []): TestResponse
    {
        return $this->json($method, "/api/v1/faculties/{$this->faculty->id}/schedule-publication?period=".urlencode(self::PERIOD), $body);
    }

    private function handIn(StudyProgram $program, User $admin): void
    {
        Sanctum::actingAs($admin);
        $this->step($program, 'submit')->assertOk();
        Sanctum::actingAs($this->facultyAdmin);
    }

    public function test_program_hands_in_and_takes_back_its_schedule(): void
    {
        Sanctum::actingAs($this->informaticsAdmin);
        $this->getJson("/api/v1/study-programs/{$this->informatics->id}/schedule-submission?period=".urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.scheduling_run.id', $this->runs[$this->informatics->id]->id)
            ->assertJsonPath('data.problems', [])
            ->assertJsonPath('data.cells_count', 1);

        $this->step($this->informatics, 'submit')->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->step($this->informatics, 'submit')->assertStatus(409);
        $this->step($this->informatics, 'withdraw')->assertOk()->assertJsonPath('data.status', 'draft');
        // There is no acceptance step: handing in is the last move before publication.
        $this->step($this->informatics, 'accept')->assertNotFound();
        $this->assertDatabaseHas('activity_logs', ['event' => 'program_schedule.withdrawn']);
    }

    public function test_submit_needs_an_active_run_that_still_fits(): void
    {
        Sanctum::actingAs($this->informaticsAdmin);
        $this->runs[$this->informatics->id]->update(['is_active' => false]);
        $this->step($this->informatics, 'submit')->assertStatus(409);

        $this->runs[$this->informatics->id]->update(['is_active' => true]);
        // The faculty took the cell back.
        RoomSlotAllocation::query()->where('study_program_id', $this->informatics->id)->delete();
        $this->getJson("/api/v1/study-programs/{$this->informatics->id}/schedule-submission?period=".urlencode(self::PERIOD))
            ->assertJsonPath('data.problems.cell', 1);
        $this->step($this->informatics, 'submit')->assertStatus(409);
    }

    public function test_faculty_reviews_and_returns_with_a_note(): void
    {
        Sanctum::actingAs($this->informaticsAdmin);
        $this->step($this->informatics, 'submit')->assertOk();

        Sanctum::actingAs($this->facultyAdmin);
        $this->getJson("/api/v1/faculties/{$this->faculty->id}/schedule-submissions?period=".urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.study_program.code', 'IF')
            ->assertJsonPath('data.0.status', 'submitted');
        $this->step($this->informatics, 'return')->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->step($this->informatics, 'return', ['note' => 'Kelas A bentrok praktikum'])
            ->assertOk()->assertJsonPath('data.status', 'returned')->assertJsonPath('data.return_note', 'Kelas A bentrok praktikum');

        // Returned = editable again: the program may move meetings and hand it in again.
        Sanctum::actingAs($this->informaticsAdmin);
        $this->step($this->informatics, 'submit')->assertOk();
        Sanctum::actingAs(User::factory()->facultyAdmin(Faculty::factory()->create())->create());
        $this->step($this->informatics, 'return', ['note' => 'x'])->assertForbidden();
    }

    public function test_publication_waits_for_every_program_then_locks_everything(): void
    {
        $this->handIn($this->informatics, $this->informaticsAdmin);
        $this->publication()->assertStatus(409);
        $this->publication('get')->assertOk()->assertJsonPath('data.can_publish', false)->assertJsonPath('data.waiting_programs.0.code', 'SI');

        $this->handIn($this->systems, $this->systemsAdmin);
        $this->publication('get')->assertJsonPath('data.can_publish', true);
        $checksum = $this->publication()->assertCreated()
            ->assertJsonPath('data.published', true)
            ->json('data.checksum');
        $this->assertStringStartsWith('sha256:', $checksum);
        $this->assertNotNull($this->runs[$this->informatics->id]->fresh()->published_at);
        $this->assertSame(2, ProgramScheduleSubmission::query()->where('status', SubmissionStatus::PUBLISHED)->count());
        $this->publication()->assertStatus(409);

        // Final: no review step, no cell change, no override, no activation.
        $this->step($this->informatics, 'return', ['note' => 'Ubah'])->assertStatus(409);
        $this->putJson('/api/v1/room-slot-allocations', ['period' => self::PERIOD, 'cells' => [
            ['time_slot_id' => $this->slots[0]->id, 'room_id' => $this->room->id, 'study_program_id' => null],
        ]])->assertStatus(409);
        Sanctum::actingAs($this->informaticsAdmin);
        $detail = ScheduleDetail::query()->where('scheduling_run_id', $this->runs[$this->informatics->id]->id)->firstOrFail();
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->room->id])->assertStatus(409);
        $other = $this->programRun($this->informatics, self::PERIOD, active: false);
        $this->postJson("/api/v1/scheduling-runs/{$other->id}/activate")->assertStatus(409);

        // Lecturers now see it.
        Sanctum::actingAs(User::factory()->lecturer()->create());
        $this->getJson('/api/v1/schedules?faculty_id='.$this->faculty->id)->assertOk()->assertJsonCount(2, 'data.schedule_details');
    }

    public function test_only_superadmin_withdraws_a_publication(): void
    {
        $this->handIn($this->informatics, $this->informaticsAdmin);
        $this->handIn($this->systems, $this->systemsAdmin);
        $this->publication()->assertCreated();

        $this->publication('delete', ['note' => 'Salah input'])->assertForbidden();

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->publication('delete')->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->publication('delete', ['note' => 'Salah input ruang'])->assertOk()->assertJsonPath('data.published', false);
        $this->assertFalse(SchedulePublication::query()->exists());
        $this->assertNull($this->runs[$this->informatics->id]->fresh()->published_at);
        $this->assertSame(2, ProgramScheduleSubmission::query()->where('status', SubmissionStatus::SUBMITTED)->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'schedule_publication.withdrawn']);
    }

    public function test_programs_cannot_publish(): void
    {
        $this->handIn($this->informatics, $this->informaticsAdmin);
        $this->handIn($this->systems, $this->systemsAdmin);

        Sanctum::actingAs($this->informaticsAdmin);
        $this->publication()->assertForbidden();
        $this->getJson("/api/v1/faculties/{$this->faculty->id}/schedule-submissions?period=".urlencode(self::PERIOD))->assertForbidden();
    }
}
