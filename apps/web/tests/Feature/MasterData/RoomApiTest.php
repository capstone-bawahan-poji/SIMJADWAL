<?php

namespace Tests\Feature\MasterData;

use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->faculty = Faculty::factory()->create();
    }

    public function test_faculty_admin_creates_room_in_own_faculty_by_default(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->postJson('/api/v1/rooms', ['code' => 'B207', 'name' => 'Lab Komputer', 'capacity' => 40])
            ->assertCreated()
            ->assertJsonPath('data.faculty_id', $this->faculty->id)
            ->assertJsonPath('data.is_shared', false)
            ->assertJsonPath('data.faculty.code', $this->faculty->code);

        $this->postJson('/api/v1/rooms', ['code' => 'E101', 'capacity' => 40, 'faculty_id' => null])->assertForbidden();
        $this->postJson('/api/v1/rooms', ['code' => 'E102', 'capacity' => 40, 'faculty_id' => Faculty::factory()->create()->id])->assertForbidden();
    }

    public function test_superadmin_creates_shared_room(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/rooms', ['code' => 'E101', 'capacity' => 40])
            ->assertCreated()
            ->assertJsonPath('data.is_shared', true);
    }

    public function test_faculty_admin_lists_own_and_shared_rooms_only(): void
    {
        Room::factory()->create(['faculty_id' => $this->faculty->id]);
        Room::factory()->shared()->create();
        Room::factory()->create();
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->getJson('/api/v1/rooms')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/rooms?shared=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_room_with_tpb_classes_stays_shared_and_cannot_be_deleted(): void
    {
        $room = Room::factory()->shared()->create();
        $group = TpbGroup::factory()->create();
        CourseLecturer::factory()->create(['course_id' => $group->course_id, 'tpb_group_id' => $group->id, 'room_id' => $room->id]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->patchJson("/api/v1/rooms/{$room->id}", ['faculty_id' => $this->faculty->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('faculty_id');

        $this->deleteJson("/api/v1/rooms/{$room->id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.teaching_assignments', 1);
    }

    public function test_delete_is_soft(): void
    {
        $room = Room::factory()->create(['faculty_id' => $this->faculty->id]);
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->deleteJson("/api/v1/rooms/{$room->id}")->assertNoContent();
        $this->assertSoftDeleted($room);
        $this->getJson("/api/v1/rooms/{$room->id}")->assertNotFound();
    }

    public function test_room_keeps_building_and_floor(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $id = $this->postJson('/api/v1/rooms', ['faculty_id' => null, 'code' => 'E101', 'building' => 'Gedung E', 'floor' => 1, 'capacity' => 40])
            ->assertCreated()
            ->assertJsonPath('data.building', 'Gedung E')
            ->assertJsonPath('data.floor', 1)
            ->json('data.id');

        $this->patchJson("/api/v1/rooms/{$id}", ['capacity' => 45])->assertOk()->assertJsonPath('data.building', 'Gedung E');
        $this->patchJson("/api/v1/rooms/{$id}", ['floor' => null])->assertOk()->assertJsonPath('data.floor', null);
        $this->patchJson("/api/v1/rooms/{$id}", ['floor' => 'dua'])->assertJsonValidationErrors('floor');
        $this->getJson('/api/v1/rooms?q=gedung e')->assertOk()->assertJsonPath('data.*.id', [$id]);
    }

    public function test_room_is_in_use_only_while_an_active_run_schedules_it(): void
    {
        [$used, $free] = Room::factory()->shared()->count(2)->create();
        $run = $this->scheduleIn($used);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/v1/rooms/{$used->id}")->assertOk()->assertJsonPath('data.is_in_use', true);
        $this->getJson("/api/v1/rooms/{$free->id}")->assertOk()->assertJsonPath('data.is_in_use', false);
        $this->getJson('/api/v1/rooms?in_use=1')->assertOk()->assertJsonPath('data.*.id', [$used->id]);
        $this->getJson('/api/v1/rooms?in_use=0')->assertOk()->assertJsonPath('data.*.id', [$free->id]);

        $run->update(['is_active' => false]);
        $this->getJson("/api/v1/rooms/{$used->id}")->assertOk()->assertJsonPath('data.is_in_use', false);
    }

    private function scheduleIn(Room $room): SchedulingRun
    {
        $course = Course::factory()->create();
        $run = SchedulingRun::query()->create([
            'batch_id' => Str::uuid()->toString(),
            'faculty_id' => $course->studyProgram->faculty_id,
            'period' => '2026/2027-1',
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'is_active' => true,
            'triggered_by' => User::factory()->create()->id,
        ]);

        ScheduleDetail::query()->create([
            'scheduling_run_id' => $run->id,
            'course_id' => $course->id,
            'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create()->id,
            'time_slot_id' => TimeSlot::factory()->create()->id,
            'room_id' => $room->id,
        ]);

        return $run;
    }
}
