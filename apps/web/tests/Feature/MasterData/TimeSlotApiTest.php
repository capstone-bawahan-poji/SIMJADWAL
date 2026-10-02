<?php

namespace Tests\Feature\MasterData;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class TimeSlotApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

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
        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['end_time' => '10:10'])->assertOk()->assertJsonPath('data.end_time', '10:10');
    }

    public function test_used_slot_cannot_change_or_be_deleted(): void
    {
        $slot = TimeSlot::factory()->create();
        TpbGroup::factory()->create(['time_slot_id' => $slot->id]);

        $this->patchJson("/api/v1/time-slots/{$slot->id}", ['type' => 2])
            ->assertStatus(409)
            ->assertJsonPath('errors.references.tpb_groups', 1);
        $this->deleteJson("/api/v1/time-slots/{$slot->id}")->assertStatus(409);
    }

    public function test_everyone_reads_slots_but_only_superadmin_writes(): void
    {
        TimeSlot::factory()->create();
        Sanctum::actingAs(User::factory()->lecturer()->create());

        $this->getJson('/api/v1/time-slots')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/time-slots', [])->assertForbidden();
    }
}
