<?php

namespace Tests\Feature\MasterData;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use App\Models\MasterData\TpbGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
