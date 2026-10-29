<?php

namespace Tests\Feature\Shared;

use App\Events\Shared\ResourcesChanged;
use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\User;
use App\Services\Shared\ChangeRecorder;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * Every write, from any client, ends in one ResourcesChanged broadcast per request.
 */
class ResourceChangesTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    /**
     * Drops what setUp and factories recorded, then starts faking the event.
     */
    private function startListening(): void
    {
        $this->app->make(ChangeRecorder::class)->flush();
        Event::fake([ResourcesChanged::class]);
    }

    /**
     * @return array<string, list<int|string>|null>
     */
    private function onlyBroadcast(): array
    {
        Event::assertDispatchedTimes(ResourcesChanged::class, 1);

        return Event::dispatched(ResourcesChanged::class)->first()[0]->resources;
    }

    public function test_api_write_broadcasts_the_table_and_id_once(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->startListening();

        $id = $this->postJson('/api/v1/faculties', ['code' => 'FSTI', 'name' => 'Fakultas Sains'])->assertCreated()->json('data.id');

        $this->assertSame(['faculties' => [$id]], $this->onlyBroadcast());
    }

    public function test_reads_broadcast_nothing(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        Faculty::factory()->create();
        $this->startListening();

        $this->getJson('/api/v1/faculties')->assertOk();

        Event::assertNotDispatched(ResourcesChanged::class);
    }

    public function test_session_write_from_the_web_broadcasts_too(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $faculty = Faculty::factory()->create();
        $this->startListening();

        $this->putJson("/api/internal/faculties/{$faculty->id}", ['code' => $faculty->code, 'name' => 'Nama Baru'])->assertOk();

        $this->assertSame(['faculties' => [$faculty->id]], $this->onlyBroadcast());
    }

    public function test_writes_of_one_request_are_merged_and_large_sets_become_null(): void
    {
        $this->startListening();
        $recorder = $this->app->make(ChangeRecorder::class);

        $recorder->record('courses', 1);
        $recorder->record('courses', 2);
        $recorder->record('courses', 1);
        foreach (range(1, ChangeRecorder::MAX_IDS + 1) as $id) {
            $recorder->record('rooms', $id);
        }
        $recorder->record('lecturers');
        $recorder->record('lecturers', 9);
        $recorder->flush();

        $this->assertSame(['courses' => [1, 2], 'rooms' => null, 'lecturers' => null], $this->onlyBroadcast());
    }

    public function test_rolled_back_writes_are_not_broadcast(): void
    {
        $this->startListening();

        try {
            DB::transaction(function () {
                Faculty::factory()->create();

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        $this->app->make(ChangeRecorder::class)->flush();

        Event::assertNotDispatched(ResourcesChanged::class);
    }

    public function test_signing_in_broadcasts_nothing(): void
    {
        $user = User::factory()->create();
        $this->startListening();

        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])->assertOk();

        $this->assertNotNull($user->fresh()->remember_token);
        Event::assertNotDispatched(ResourcesChanged::class);
    }

    public function test_bulk_preference_replace_is_recorded(): void
    {
        $this->seed(ConstraintTypeSeeder::class);
        $program = StudyProgram::factory()->create();
        $lecturer = Lecturer::factory()->create(['study_program_id' => $program->id]);
        $slot = TimeSlot::factory()->create();
        $this->actingAs(User::factory()->studyProgramAdmin($program)->create());
        $this->startListening();

        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", [
            'preferences' => [['time_slot_id' => $slot->id, 'type' => 'want']],
        ])->assertOk();

        $this->assertArrayHasKey('lecturer_preferences', $this->onlyBroadcast());
    }

    public function test_changing_only_the_programs_of_a_tpb_group_is_recorded(): void
    {
        $course = Course::factory()->tpb()->create(['parallel_class_count' => 5]);
        $slot = TimeSlot::factory()->create();
        [$first, $second] = StudyProgram::factory()->count(2)->create()->all();
        $group = TpbGroup::query()->create(['course_id' => $course->id, 'code' => 'G1', 'time_slot_id' => $slot->id]);
        $group->studyPrograms()->sync([$first->id]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->startListening();

        $this->putJson("/api/v1/tpb-groups/{$group->id}", [
            'code' => 'G1',
            'time_slot_id' => $slot->id,
            'study_program_ids' => [$first->id, $second->id],
        ])->assertOk();

        $this->assertSame(['tpb_groups' => [$group->id]], $this->onlyBroadcast());
    }
}
