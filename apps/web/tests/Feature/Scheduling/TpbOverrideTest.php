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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTpbRun;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * admin_tpb moves or swaps TPB blocks (one meeting of one group, all its classes together) while
 * the TPB run is active but not published. Published runs stay final.
 */
class TpbOverrideTest extends TestCase
{
    use CreatesTpbRun, RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    /** @var list<TimeSlot> monday sessions 1-4 */
    private array $slots;

    /** @var list<TimeSlot> tuesday sessions 1-2 */
    private array $tuesday;

    private StudyProgram $informatics;

    private Room $roomA;

    private Room $roomB;

    private TpbOffering $math;

    private TpbOffering $physics;

    private Lecturer $mathA;

    private Lecturer $mathB;

    private Lecturer $physicsLecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->slots = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 1, 'session' => $session]), [1, 2, 3, 4]);
        $this->tuesday = array_map(fn (int $session) => TimeSlot::factory()->create(['day' => 2, 'session' => $session]), [1, 2]);
        $this->informatics = StudyProgram::factory()->accepted()->create(['faculty_id' => Faculty::factory()->create()->id]);
        $this->roomA = Room::factory()->shared()->create(['code' => 'A', 'capacity' => 60]);
        $this->roomB = Room::factory()->shared()->create(['code' => 'B', 'capacity' => 60]);
        $this->mathA = Lecturer::factory()->create();
        $this->mathB = Lecturer::factory()->create();
        $this->physicsLecturer = Lecturer::factory()->create();
        $this->math = $this->offering(3);
        $this->physics = $this->offering(3);

        $this->group($this->math, 'G1', TpbCohort::MABA, [1 => $this->mathA, 2 => $this->mathB]);
        $this->group($this->physics, 'G1', TpbCohort::CAMPURAN, [1 => $this->physicsLecturer]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
    }

    public function test_tpb_admin_moves_a_block_and_every_class_follows(): void
    {
        $this->tpbRun([
            $this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($this->math, 2, $this->mathB, $this->slots[0], $this->roomB),
        ]);

        $detail = $this->detail($this->math, 1);
        $this->patchJson("/api/v1/tpb-blocks/{$detail->id}", ['time_slot_id' => $this->slots[2]->id, 'reason' => 'Dosen minta pindah'])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ([1 => $this->roomA, 2 => $this->roomB] as $class => $room) {
            $moved = $this->detail($this->math, $class);
            $this->assertSame($this->slots[2]->id, $moved->time_slot_id);
            $this->assertSame($room->id, $moved->room_id);
            $this->assertTrue($moved->is_overridden);
        }
    }

    public function test_block_cannot_join_a_slot_of_another_regular_group_of_the_same_program(): void
    {
        $this->tpbRun([
            $this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($this->math, 2, $this->mathB, $this->slots[0], $this->roomB),
            $this->gene($this->physics, 1, $this->physicsLecturer, $this->slots[1], $this->roomA),
        ]);

        $this->patchJson("/api/v1/tpb-blocks/{$this->detail($this->math, 1)->id}", ['time_slot_id' => $this->slots[1]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
        $this->assertSame($this->slots[0]->id, $this->detail($this->math, 1)->time_slot_id);
    }

    public function test_block_cannot_move_to_a_slot_where_its_lecturer_teaches_another_class(): void
    {
        $retake = $this->offering(2);
        $this->group($retake, 'G9', TpbCohort::MENGULANG, [1 => $this->mathA]);
        $this->tpbRun([
            $this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($this->math, 2, $this->mathB, $this->slots[0], $this->roomB),
            $this->gene($retake, 1, $this->mathA, $this->slots[3], null),
        ]);

        $this->patchJson("/api/v1/tpb-blocks/{$this->detail($this->math, 2)->id}", ['time_slot_id' => $this->slots[3]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
    }

    public function test_block_needs_a_free_shared_room_for_every_class(): void
    {
        $retake = $this->offering(2);
        $retakeLecturer = Lecturer::factory()->create();
        $this->group($retake, 'G9', TpbCohort::MENGULANG, [1 => $retakeLecturer]);
        $this->tpbRun([
            $this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($this->math, 2, $this->mathB, $this->slots[0], $this->roomB),
            $this->gene($retake, 1, $retakeLecturer, $this->slots[2], $this->roomA),
        ]);

        $this->patchJson("/api/v1/tpb-blocks/{$this->detail($this->math, 1)->id}", ['time_slot_id' => $this->slots[2]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
    }

    public function test_two_meetings_of_a_four_sks_course_stay_on_different_days(): void
    {
        $algo = $this->offering(4);
        $this->group($algo, 'G7', TpbCohort::NON_MABA, [1 => $this->mathA]);
        $this->tpbRun([
            $this->gene($algo, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($algo, 1, $this->mathA, $this->tuesday[0], $this->roomA, meeting: 2),
        ]);

        $this->patchJson("/api/v1/tpb-blocks/{$this->detail($algo, 1)->id}", ['time_slot_id' => $this->tuesday[1]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('time_slot_id');
        $this->patchJson("/api/v1/tpb-blocks/{$this->detail($algo, 1)->id}", ['time_slot_id' => $this->slots[1]->id])->assertOk();
    }

    public function test_options_list_free_slots_with_rooms_and_swap_candidates(): void
    {
        $this->tpbRun([
            $this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($this->math, 2, $this->mathB, $this->slots[0], $this->roomB),
            $this->gene($this->physics, 1, $this->physicsLecturer, $this->slots[1], $this->roomA),
        ]);

        $response = $this->getJson("/api/v1/tpb-blocks/{$this->detail($this->math, 1)->id}/options")->assertOk();

        $response->assertJsonPath('data.current.time_slot_id', $this->slots[0]->id)
            ->assertJsonPath('data.block.group_code', 'G1')
            ->assertJsonCount(2, 'data.block.classes')
            ->assertJsonCount(6, 'data.time_slots');
        $byId = collect($response->json('data.time_slots'))->keyBy('id');
        $this->assertTrue($byId[$this->slots[2]->id]['available']);
        $this->assertCount(2, $byId[$this->slots[2]->id]['rooms']);
        $this->assertFalse($byId[$this->slots[1]->id]['available']);
        $this->assertSame([$this->physics->course_id], collect($response->json('data.swap_candidates'))->pluck('course.id')->all());
    }

    public function test_swap_exchanges_the_slots_of_two_blocks(): void
    {
        $this->tpbRun([
            $this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA),
            $this->gene($this->math, 2, $this->mathB, $this->slots[0], $this->roomB),
            $this->gene($this->physics, 1, $this->physicsLecturer, $this->slots[1], $this->roomA),
        ]);

        $this->postJson('/api/v1/tpb-blocks/swap', ['a' => $this->detail($this->math, 1)->id, 'b' => $this->detail($this->physics, 1)->id])
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->assertSame($this->slots[1]->id, $this->detail($this->math, 1)->time_slot_id);
        $this->assertSame($this->slots[1]->id, $this->detail($this->math, 2)->time_slot_id);
        $this->assertSame($this->slots[0]->id, $this->detail($this->physics, 1)->time_slot_id);
    }

    public function test_published_tpb_run_is_final(): void
    {
        $this->tpbRun([$this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA)], published: true);

        $detail = $this->detail($this->math, 1);
        $this->getJson("/api/v1/tpb-blocks/{$detail->id}/options")->assertStatus(409);
        $this->patchJson("/api/v1/tpb-blocks/{$detail->id}", ['time_slot_id' => $this->slots[2]->id])->assertStatus(409);
    }

    public function test_program_admin_cannot_override_the_tpb_run_and_old_endpoint_points_to_blocks(): void
    {
        $this->tpbRun([$this->gene($this->math, 1, $this->mathA, $this->slots[0], $this->roomA)]);
        $detail = $this->detail($this->math, 1);

        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->informatics)->create());
        $this->patchJson("/api/v1/tpb-blocks/{$detail->id}", ['time_slot_id' => $this->slots[2]->id])->assertForbidden();

        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->patchJson("/api/v1/schedule-details/{$detail->id}", ['time_slot_id' => $this->slots[2]->id, 'room_id' => $this->roomA->id])->assertStatus(409);
    }

    private function offering(int $sks): TpbOffering
    {
        return TpbOffering::factory()->create([
            'course_id' => Course::factory()->tpb()->create(['sks' => $sks])->id,
            'period' => self::PERIOD, 'parallel_class_count' => 2, 'class_capacity' => 40,
        ]);
    }

    /**
     * @param  array<int, Lecturer>  $classes  class_number => lecturer
     */
    private function group(TpbOffering $offering, string $code, TpbCohort $cohort, array $classes): TpbGroup
    {
        $group = TpbGroup::factory()->create(['tpb_offering_id' => $offering->id, 'code' => $code, 'cohort' => $cohort]);
        $group->studyPrograms()->sync([$this->informatics->id => ['target_semester' => $cohort === TpbCohort::MENGULANG ? null : 1]]);
        foreach ($classes as $number => $lecturer) {
            CourseLecturer::factory()->forTpbGroup($group)->create(['class_number' => $number, 'lecturer_id' => $lecturer->id]);
        }

        return $group;
    }

    /**
     * @return array<string, int|null>
     */
    private function gene(TpbOffering $offering, int $class, Lecturer $lecturer, TimeSlot $slot, ?Room $room, int $meeting = 1): array
    {
        return ['course_id' => $offering->course_id, 'class_number' => $class, 'meeting_number' => $meeting, 'lecturer_id' => $lecturer->id, 'time_slot_id' => $slot->id, 'room_id' => $room?->id];
    }

    /**
     * @param  list<array<string, int|null>>  $genes
     */
    private function tpbRun(array $genes, bool $published = false): SchedulingRun
    {
        return $this->activeTpbRun(self::PERIOD, $genes, $published);
    }

    private function detail(TpbOffering $offering, int $class, int $meeting = 1): ScheduleDetail
    {
        return ScheduleDetail::query()->where('course_id', $offering->course_id)->where('class_number', $class)->where('meeting_number', $meeting)->sole();
    }
}
