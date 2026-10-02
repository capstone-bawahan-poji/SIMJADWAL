<?php

namespace Tests\Feature\MasterData;

use App\Enums\MasterData\Day;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * TPB groups are placed by hand: a program, a lecturer or a room may not be in two
 * TPB places of one slot (same semester for programs, same parity for lecturers and rooms).
 */
class TpbApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Course $calculus;

    private Course $physics;

    private TimeSlot $tuesday3;

    private TimeSlot $wednesday1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->calculus = Course::factory()->tpb()->create(['name' => 'Kalkulus', 'parallel_class_count' => 10]);
        $this->physics = Course::factory()->tpb()->create(['name' => 'Fisika Dasar', 'parallel_class_count' => 5]);
        $this->tuesday3 = TimeSlot::factory()->create(['day' => Day::TUESDAY, 'session' => 3]);
        $this->wednesday1 = TimeSlot::factory()->create(['day' => Day::WEDNESDAY, 'session' => 1]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
    }

    public function test_tpb_admin_creates_group_of_programs_from_several_faculties(): void
    {
        $programs = StudyProgram::factory()->count(3)->create();

        $this->postJson('/api/v1/tpb-groups', [
            'course_id' => $this->calculus->id,
            'code' => 'KAL-G1',
            'time_slot_id' => $this->tuesday3->id,
            'study_program_ids' => $programs->modelKeys(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.course.name', 'Kalkulus')
            ->assertJsonPath('data.time_slot.session', 3)
            ->assertJsonCount(3, 'data.study_programs');
    }

    public function test_group_needs_a_tpb_course(): void
    {
        $this->postJson('/api/v1/tpb-groups', [
            'course_id' => Course::factory()->create()->id,
            'code' => 'X',
            'study_program_ids' => [StudyProgram::factory()->create()->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');
    }

    public function test_program_cannot_attend_two_groups_of_one_semester_in_one_slot(): void
    {
        $informatics = StudyProgram::factory()->create(['code' => 'IF']);
        $this->group($this->calculus, 'KAL-G1', $this->tuesday3, [$informatics]);

        $this->postJson('/api/v1/tpb-groups', [
            'course_id' => $this->physics->id,
            'code' => 'FIS-G1',
            'time_slot_id' => $this->tuesday3->id,
            'study_program_ids' => [$informatics->id],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.study_program_ids.0', 'Program studi IF sudah mengikuti grup TPB KAL-G1 (Kalkulus) pada slot ini.');

        $this->assertDatabaseMissing('tpb_groups', ['code' => 'FIS-G1']);

        // Another semester is another cohort of students.
        $secondSemester = Course::factory()->tpb()->create(['semester' => 2]);
        $this->postJson('/api/v1/tpb-groups', [
            'course_id' => $secondSemester->id,
            'code' => 'S2-G1',
            'time_slot_id' => $this->tuesday3->id,
            'study_program_ids' => [$informatics->id],
        ])->assertCreated();
    }

    public function test_tpb_class_rules(): void
    {
        $group = $this->group($this->calculus, 'KAL-G1', $this->tuesday3);
        $otherGroup = $this->group($this->physics, 'FIS-G1', null);
        $lecturer = Lecturer::factory()->create();
        $sharedRoom = Room::factory()->shared()->create();

        $this->postJson('/api/v1/teaching-assignments', ['course_id' => $this->calculus->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tpb_group_id');

        $this->postJson('/api/v1/teaching-assignments', ['course_id' => $this->calculus->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id, 'tpb_group_id' => $otherGroup->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tpb_group_id');

        $this->postJson('/api/v1/teaching-assignments', [
            'course_id' => $this->calculus->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id,
            'tpb_group_id' => $group->id, 'room_id' => Room::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonPath('errors.room_id.0', 'Kelas TPB hanya boleh memakai ruangan bersama.');

        $this->postJson('/api/v1/teaching-assignments', [
            'course_id' => $this->calculus->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id,
            'tpb_group_id' => $group->id, 'room_id' => $sharedRoom->id,
        ])->assertCreated()->assertJsonPath('data.room.code', $sharedRoom->code);
    }

    public function test_lecturer_and_room_cannot_hold_two_tpb_classes_in_one_slot(): void
    {
        $group = $this->group($this->calculus, 'KAL-G1', $this->tuesday3);
        $lecturer = Lecturer::factory()->create();
        $room = Room::factory()->shared()->create();
        $this->tpbClass($this->calculus, 1, $group, $lecturer, $room);

        $this->postJson('/api/v1/teaching-assignments', [
            'course_id' => $this->calculus->id, 'class_number' => 2, 'lecturer_id' => $lecturer->id,
            'tpb_group_id' => $group->id, 'room_id' => $room->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.lecturer_id.0', 'Dosen sudah mengajar Kalkulus kelas A pada slot ini.')
            ->assertJsonPath('errors.room_id.0', 'Ruangan sudah dipakai Kalkulus kelas A pada slot ini.');

        $this->assertSame(1, CourseLecturer::count());
    }

    public function test_moving_a_group_rechecks_its_classes_and_rolls_back(): void
    {
        $lecturer = Lecturer::factory()->create();
        $calculusGroup = $this->group($this->calculus, 'KAL-G1', $this->tuesday3);
        $physicsGroup = $this->group($this->physics, 'FIS-G1', $this->wednesday1);
        $this->tpbClass($this->calculus, 1, $calculusGroup, $lecturer);
        $this->tpbClass($this->physics, 1, $physicsGroup, $lecturer);

        $this->patchJson("/api/v1/tpb-groups/{$physicsGroup->id}", ['time_slot_id' => $this->tuesday3->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.time_slot_id.0', 'Kelas A: Dosen sudah mengajar Kalkulus kelas A pada slot ini.');

        $this->assertSame($this->wednesday1->id, $physicsGroup->fresh()->time_slot_id);
    }

    public function test_group_with_classes_cannot_be_deleted(): void
    {
        $group = $this->group($this->calculus, 'KAL-G1', $this->tuesday3);
        $this->tpbClass($this->calculus, 1, $group, Lecturer::factory()->create());

        $this->deleteJson("/api/v1/tpb-groups/{$group->id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.teaching_assignments', 1);
    }

    public function test_program_admin_reads_groups_and_blocked_slots_but_cannot_write(): void
    {
        $informatics = StudyProgram::factory()->create();
        $this->group($this->calculus, 'KAL-G1', $this->tuesday3, [$informatics]);
        $this->group($this->physics, 'FIS-G1', null, [$informatics]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($informatics)->create());

        $this->getJson('/api/v1/tpb-groups')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/study-programs/{$informatics->id}/tpb-blocked-slots")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'KAL-G1')
            ->assertJsonPath('data.0.time_slot_id', $this->tuesday3->id);

        $this->postJson('/api/v1/tpb-groups', [])->assertForbidden();
        $this->postJson('/api/v1/teaching-assignments', [
            'course_id' => $this->calculus->id, 'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create()->id, 'tpb_group_id' => TpbGroup::first()->id,
        ])->assertForbidden();
    }

    /**
     * @param  list<StudyProgram>  $programs
     */
    private function group(Course $course, string $code, ?TimeSlot $slot, array $programs = []): TpbGroup
    {
        $group = TpbGroup::factory()->create(['course_id' => $course->id, 'code' => $code, 'time_slot_id' => $slot?->id]);
        $group->studyPrograms()->sync(array_map(fn (StudyProgram $program) => $program->id, $programs) ?: [StudyProgram::factory()->create()->id]);

        return $group;
    }

    private function tpbClass(Course $course, int $number, TpbGroup $group, Lecturer $lecturer, ?Room $room = null): CourseLecturer
    {
        return CourseLecturer::factory()->create([
            'course_id' => $course->id,
            'class_number' => $number,
            'lecturer_id' => $lecturer->id,
            'tpb_group_id' => $group->id,
            'room_id' => $room?->id,
        ]);
    }
}
