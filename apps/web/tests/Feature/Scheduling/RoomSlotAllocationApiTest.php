<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\SlotRequestStatus;
use App\Enums\Scheduling\SubmissionStatus;
use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\SlotRequest;
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
 * POS 040/IT10.1/OT.07/2026 steps 1, 3-6: the faculty hands cells to its programs, a program checks
 * whether they hold its classes and asks for more.
 */
class RoomSlotAllocationApiTest extends TestCase
{
    use BuildsProgramSchedules, CreatesTpbRun, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $informatics;

    private StudyProgram $systems;

    private User $facultyAdmin;

    /** @var list<TimeSlot> */
    private array $slots;

    private Room $own;

    private Room $shared;

    private Room $tpbRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);
        $this->faculty = Faculty::factory()->create();
        $this->informatics = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'IF']);
        $this->systems = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'SI']);
        $this->facultyAdmin = User::factory()->facultyAdmin($this->faculty)->create();
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 1, 'session' => $session, 'type' => $session === 1 ? 3 : 2]), [1, 2, 3]);
        $this->own = Room::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'E101', 'capacity' => 40]);
        $this->shared = Room::factory()->create(['faculty_id' => null, 'code' => 'G201', 'capacity' => 80]);
        $this->tpbRoom = Room::factory()->create(['faculty_id' => null, 'code' => 'F101', 'capacity' => 60]);

        $math = Course::factory()->tpb()->create(['sks' => 3]);
        $this->activeTpbRun(self::PERIOD, [[
            'course_id' => $math->id, 'class_number' => 1, 'lecturer_id' => Lecturer::factory()->create()->id,
            'time_slot_id' => $this->slots[0]->id, 'room_id' => $this->tpbRoom->id,
        ]]);

        Sanctum::actingAs($this->facultyAdmin);
    }

    private function cell(int $slot, Room $room, ?StudyProgram $program): array
    {
        return ['time_slot_id' => $this->slots[$slot]->id, 'room_id' => $room->id, 'study_program_id' => $program?->id];
    }

    private function sync(array $cells): TestResponse
    {
        return $this->putJson('/api/v1/room-slot-allocations', ['period' => self::PERIOD, 'cells' => $cells]);
    }

    public function test_faculty_hands_out_and_takes_back_cells(): void
    {
        $this->sync([$this->cell(0, $this->own, $this->informatics), $this->cell(1, $this->shared, $this->systems)])
            ->assertOk()
            ->assertJsonPath('data.allocated', 2)
            ->assertJsonPath('data.grid.study_programs.0.cells_count', 1)
            ->assertJsonPath('data.grid.study_programs.0.required_meetings', 0)
            ->assertJsonPath('data.grid.study_programs.0.sufficient', true);
        $this->assertSame(2, RoomSlotAllocation::query()->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'room_slot_allocation.synced']);

        // Move one cell to the other program (a swap between programs), release the other.
        $this->sync([$this->cell(0, $this->own, $this->systems), $this->cell(1, $this->shared, null)])
            ->assertOk()->assertJsonPath('data.allocated', 1)->assertJsonPath('data.released', 1);
        $this->assertSame($this->systems->id, RoomSlotAllocation::query()->sole()->study_program_id);
    }

    public function test_demo_fill_gives_each_program_the_cells_it_is_short_of(): void
    {
        Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 3, 'sks' => 3, 'parallel_class_count' => 2, 'class_capacity' => 35]);

        $this->postJson('/api/internal/room-slot-allocations/auto-fill?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.programs.0.code', 'IF')
            ->assertJsonPath('data.programs.0.added', 2)
            ->assertJsonPath('data.programs.0.sufficient', true)
            ->assertJsonPath('data.programs.1.added', 0)
            ->assertJsonPath('data.grid.study_programs.0.sufficient', true);

        // Only the free rooms of a type-3 slot: the TPB room stays TPB's.
        $this->assertSame(2, RoomSlotAllocation::query()->where('study_program_id', $this->informatics->id)->count());
        $this->assertSame(0, RoomSlotAllocation::query()->where('room_id', $this->tpbRoom->id)->count());

        // Running it again changes nothing.
        $this->postJson('/api/internal/room-slot-allocations/auto-fill?period='.urlencode(self::PERIOD))
            ->assertOk()->assertJsonPath('data.programs.0.added', 0);
    }

    public function test_grid_shows_tpb_other_faculties_and_allocations(): void
    {
        $other = StudyProgram::factory()->create();
        $this->giveCells($other, self::PERIOD, [[$this->slots[2], $this->shared]]);
        $this->giveCells($this->informatics, self::PERIOD, [[$this->slots[1], $this->own]]);
        Room::factory()->create(['faculty_id' => Faculty::factory()->create()->id]);

        $grid = $this->getJson('/api/v1/room-slot-allocations?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.tpb_source.type', 'run')
            ->assertJsonPath('data.published', false)
            ->assertJsonCount(3, 'data.rooms')
            ->assertJsonCount(3, 'data.time_slots')
            ->json('data.cells');

        $byKey = collect($grid)->keyBy(fn (array $c) => "{$c['time_slot_id']}:{$c['room_id']}");
        $this->assertSame('tpb', $byKey["{$this->slots[0]->id}:{$this->tpbRoom->id}"]['status']);
        $this->assertSame('allocated', $byKey["{$this->slots[1]->id}:{$this->own->id}"]['status']);
        $this->assertSame($this->informatics->id, $byKey["{$this->slots[1]->id}:{$this->own->id}"]['study_program_id']);
        $this->assertSame('other_faculty', $byKey["{$this->slots[2]->id}:{$this->shared->id}"]['status']);
        $this->assertCount(3, $grid);
    }

    public function test_cells_that_are_not_the_faculty_to_give_are_refused(): void
    {
        $foreignRoom = Room::factory()->create(['faculty_id' => Faculty::factory()->create()->id]);
        $foreignProgram = StudyProgram::factory()->create();
        $this->giveCells($foreignProgram, self::PERIOD, [[$this->slots[2], $this->shared]]);

        $this->sync([
            $this->cell(0, $this->tpbRoom, $this->informatics),
            $this->cell(1, $foreignRoom, $this->informatics),
            $this->cell(1, $this->own, $foreignProgram),
            $this->cell(2, $this->shared, $this->informatics),
            $this->cell(1, $this->shared, $this->informatics),
            $this->cell(1, $this->shared, $this->systems),
        ])->assertUnprocessable()->assertJsonValidationErrors(['cells.0', 'cells.1.room_id', 'cells.2.study_program_id', 'cells.3', 'cells.5']);

        $this->assertSame(1, RoomSlotAllocation::query()->count());
    }

    public function test_cells_wait_for_tpb_data_and_lock_with_handed_in_schedules(): void
    {
        $this->putJson('/api/v1/room-slot-allocations', ['period' => '2030/2031-GANJIL', 'cells' => [$this->cell(1, $this->own, $this->informatics)]])
            ->assertStatus(409);

        $this->giveCells($this->informatics, self::PERIOD, [[$this->slots[1], $this->own]]);
        $this->setSubmission($this->informatics, self::PERIOD, SubmissionStatus::SUBMITTED);
        $this->sync([$this->cell(1, $this->own, null)])->assertUnprocessable()->assertJsonValidationErrors('cells.0');
        $this->sync([$this->cell(1, $this->own, $this->systems)])->assertUnprocessable();

        $this->setSubmission($this->informatics, self::PERIOD, SubmissionStatus::RETURNED);
        $this->sync([$this->cell(1, $this->own, null)])->assertOk();
    }

    public function test_who_may_see_and_change_the_grid(): void
    {
        $programAdmin = User::factory()->studyProgramAdmin($this->informatics)->create();
        Sanctum::actingAs($programAdmin);
        $this->getJson('/api/v1/room-slot-allocations?period='.urlencode(self::PERIOD))->assertOk();
        $this->sync([$this->cell(1, $this->own, $this->informatics)])->assertForbidden();

        Sanctum::actingAs(User::factory()->facultyAdmin(Faculty::factory()->create())->create());
        $this->getJson("/api/v1/study-programs/{$this->informatics->id}/room-slots?period=".urlencode(self::PERIOD))->assertForbidden();

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->getJson('/api/v1/room-slot-allocations?period='.urlencode(self::PERIOD))->assertUnprocessable()->assertJsonValidationErrors('faculty_id');
        $this->putJson('/api/v1/room-slot-allocations', ['period' => self::PERIOD, 'faculty_id' => $this->faculty->id, 'cells' => [$this->cell(1, $this->own, $this->informatics)]])
            ->assertOk();
    }

    public function test_sufficiency_counts_meetings_against_cells_that_fit(): void
    {
        // Two classes of a 3 SKS course and one 4 SKS class: four meetings; the 4 SKS needs 70 seats.
        Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 3, 'sks' => 3, 'parallel_class_count' => 2, 'class_capacity' => 35]);
        $big = Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 3, 'sks' => 4, 'parallel_class_count' => 1, 'class_capacity' => 70]);
        Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 4, 'sks' => 3, 'class_capacity' => 35]);
        $this->giveCells($this->informatics, self::PERIOD, [[$this->slots[1], $this->own], [$this->slots[2], $this->own], [$this->slots[1], $this->shared]]);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->informatics)->create());
        $this->getJson("/api/v1/study-programs/{$this->informatics->id}/room-slots?period=".urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonCount(3, 'data.cells')
            ->assertJsonPath('data.sufficiency.allocated_cells', 3)
            ->assertJsonPath('data.sufficiency.required_meetings', 4)
            ->assertJsonPath('data.sufficiency.sufficient', false)
            ->assertJsonPath('data.sufficiency.shortages', [[
                'course_id' => $big->id, 'code' => $big->code, 'name' => $big->name, 'meetings' => 1, 'min_capacity' => 70, 'slot_type' => 2,
            ]]);
    }

    public function test_slot_request_from_shortage_to_fulfilment(): void
    {
        $big = Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 3, 'sks' => 3, 'parallel_class_count' => 2, 'class_capacity' => 70]);
        $this->giveCells($this->informatics, self::PERIOD, [[$this->slots[1], $this->shared]]);
        $programAdmin = User::factory()->studyProgramAdmin($this->informatics)->create();
        Sanctum::actingAs($programAdmin);

        $id = $this->postJson('/api/v1/slot-requests', ['period' => self::PERIOD, 'note' => 'Kelas besar'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.needs.0.course_id', $big->id)
            ->assertJsonPath('data.needs.0.meetings', 1)
            ->assertJsonPath('data.needs.0.min_capacity', 70)
            ->assertJsonPath('data.needs.0.code', $big->code)
            ->json('data.id');
        $this->postJson('/api/v1/slot-requests', ['period' => self::PERIOD])->assertStatus(409);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->systems)->create());
        $this->getJson('/api/v1/slot-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/slot-requests/{$id}/fulfill", ['cells' => [['time_slot_id' => $this->slots[2]->id, 'room_id' => $this->shared->id]]])->assertForbidden();

        Sanctum::actingAs($this->facultyAdmin);
        $this->getJson('/api/v1/slot-requests?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/slot-requests/{$id}/fulfill", ['cells' => [['time_slot_id' => $this->slots[2]->id, 'room_id' => $this->shared->id]], 'response_note' => 'Pakai G201'])
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled')
            ->assertJsonPath('data.allocations_count', 1);
        $this->assertSame($id, RoomSlotAllocation::query()->where('time_slot_id', $this->slots[2]->id)->value('slot_request_id'));
        $this->postJson("/api/v1/slot-requests/{$id}/reject", ['note' => 'Terlambat'])->assertStatus(409);
    }

    public function test_slot_request_rejected_partially_fulfilled_or_cancelled(): void
    {
        Course::factory()->create(['study_program_id' => $this->informatics->id, 'semester' => 3, 'sks' => 3, 'parallel_class_count' => 3, 'class_capacity' => 30]);
        $programAdmin = User::factory()->studyProgramAdmin($this->informatics)->create();
        $request = fn () => SlotRequest::query()->create([
            'period' => self::PERIOD, 'study_program_id' => $this->informatics->id, 'requested_by' => $programAdmin->id,
            'needs' => [['course_id' => 1, 'meetings' => 3, 'min_capacity' => 30]],
        ]);

        $first = $request();
        $this->postJson("/api/v1/slot-requests/{$first->id}/reject", [])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/slot-requests/{$first->id}/reject", ['note' => 'Tidak ada ruang tersisa, diteruskan ke TPB Pusat'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $second = $request();
        $this->postJson("/api/v1/slot-requests/{$second->id}/fulfill", ['cells' => [['time_slot_id' => $this->slots[1]->id, 'room_id' => $this->own->id]]])
            ->assertOk()->assertJsonPath('data.status', SlotRequestStatus::PARTIALLY_FULFILLED->value);

        $third = $request();
        $this->deleteJson("/api/v1/slot-requests/{$third->id}")->assertForbidden();
        Sanctum::actingAs($programAdmin);
        $this->deleteJson("/api/v1/slot-requests/{$third->id}")->assertNoContent();
        $this->assertDatabaseMissing('slot_requests', ['id' => $third->id]);
    }
}
