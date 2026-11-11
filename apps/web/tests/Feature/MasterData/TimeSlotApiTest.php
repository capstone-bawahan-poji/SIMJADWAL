<?php

namespace Tests\Feature\MasterData;

use App\Enums\MasterData\Day;
use App\Models\MasterData\Course;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTpbRun;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class TimeSlotApiTest extends TestCase
{
    use CreatesTpbRun, RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Sanctum::actingAs(User::factory()->superAdmin()->create());
    }

    public function test_superadmin_creates_slot_with_hh_mm_times(): void
    {
        $this->postJson('/api/v1/time-slots', ['day' => 2, 'session' => 1, 'start_time' => '07:30', 'end_time' => '10:00', 'type' => 3])
            ->assertCreated()
            ->assertJsonPath('data.day', 2)
            ->assertJsonPath('data.day_label', 'Selasa')
            ->assertJsonPath('data.start_time', '07:30')
            ->assertJsonPath('data.end_time', '10:00');

        $this->postJson('/api/v1/time-slots', ['day' => 2, 'session' => 1, 'start_time' => '10:20', 'end_time' => '10:00', 'type' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session', 'end_time', 'type']);
    }

    public function test_patch_checks_merged_values(): void
    {
        TimeSlot::factory()->create(['day' => Day::MONDAY, 'session' => 2, 'start_time' => '10:20', 'end_time' => '12:00', 'type' => 2]);
        $slot = TimeSlot::factory()->create(['day' => Day::MONDAY, 'session' => 1, 'start_time' => '07:30', 'end_time' => '10:00']);

        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['session' => 2])->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['end_time' => '07:00'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['end_time' => '10:10'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['end_time' => '09:10', 'type' => 2])->assertOk()->assertJsonPath('data.end_time', '09:10');
    }

    public function test_used_slot_cannot_change_or_be_deleted(): void
    {
        $slot = TimeSlot::factory()->create();
        $this->activeTpbRun('2026/2027-GANJIL', [[
            'course_id' => Course::factory()->tpb()->create()->id, 'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create()->id, 'time_slot_id' => $slot->id, 'room_id' => null,
        ]]);

        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['session' => 2])
            ->assertStatus(409)
            ->assertJsonPath('errors.references.schedules', 1);
        $this->deleteJson("/api/v1/time-slots/{$slot->id}")->assertStatus(409);
    }

    public function test_used_slot_type_change_needs_matching_time_range(): void
    {
        $slot = TimeSlot::factory()->create(); // 08:00-10:30, 3 SKS
        $this->activeTpbRun('2026/2027-GANJIL', [[
            'course_id' => Course::factory()->tpb()->create()->id, 'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create()->id, 'time_slot_id' => $slot->id, 'room_id' => null,
        ]]);

        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['type' => 2])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['type' => 2, 'end_time' => '09:40'])
            ->assertOk()
            ->assertJsonPath('data.type', 2)
            ->assertJsonPath('data.in_use', true);
    }

    public function test_store_and_patch_check_time_range_against_type(): void
    {
        $this->postJson('/api/v1/time-slots', ['day' => 2, 'session' => 1, 'start_time' => '07:30', 'end_time' => '09:00', 'type' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');

        $slot = TimeSlot::factory()->create();
        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['end_time' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
    }

    public function test_used_slot_time_stays_editable_but_day_session_type_locked(): void
    {
        $slot = TimeSlot::factory()->create(['day' => Day::FRIDAY, 'session' => 3, 'start_time' => '13:30', 'end_time' => '15:30', 'type' => 3]);
        $this->activeTpbRun('2026/2027-GANJIL', [[
            'course_id' => Course::factory()->tpb()->create()->id, 'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create()->id, 'time_slot_id' => $slot->id, 'room_id' => null,
        ]]);

        $this->getJson('/api/v1/time-slots')->assertOk()->assertJsonPath('data.0.in_use', true);

        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['start_time' => '13:00', 'end_time' => '15:30'])
            ->assertOk()
            ->assertJsonPath('data.start_time', '13:00')
            ->assertJsonPath('data.end_time', '15:30');

        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['session' => 4])->assertStatus(409);
    }

    public function test_everyone_reads_slots_but_only_superadmin_writes(): void
    {
        TimeSlot::factory()->create();
        Sanctum::actingAs(User::factory()->lecturer()->create());

        $this->getJson('/api/v1/time-slots')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/time-slots', [])->assertForbidden();
    }
}
