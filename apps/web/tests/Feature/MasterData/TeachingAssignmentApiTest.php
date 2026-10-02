<?php

namespace Tests\Feature\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class TeachingAssignmentApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private StudyProgram $program;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->program = StudyProgram::factory()->create();
        $this->course = Course::factory()->create(['study_program_id' => $this->program->id, 'parallel_class_count' => 2]);
    }

    public function test_class_may_be_taught_by_lecturer_of_another_faculty(): void
    {
        $outsider = Lecturer::factory()->create();
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->postJson('/api/v1/teaching-assignments', ['course_id' => $this->course->id, 'class_number' => 2, 'lecturer_id' => $outsider->id])
            ->assertCreated()
            ->assertJsonPath('data.class_label', 'B')
            ->assertJsonPath('data.lecturer.nip', $outsider->nip)
            ->assertJsonPath('data.room', null);
    }

    public function test_class_number_must_exist_and_be_free(): void
    {
        CourseLecturer::factory()->create(['course_id' => $this->course->id, 'class_number' => 1]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());
        $lecturer = Lecturer::factory()->create();

        $this->postJson('/api/v1/teaching-assignments', ['course_id' => $this->course->id, 'class_number' => 1, 'lecturer_id' => $lecturer->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('class_number');

        $this->postJson('/api/v1/teaching-assignments', ['course_id' => $this->course->id, 'class_number' => 3, 'lecturer_id' => $lecturer->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.class_number.0', 'Mata kuliah ini hanya punya 2 kelas.');
    }

    public function test_regular_class_takes_no_group_or_room(): void
    {
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->postJson('/api/v1/teaching-assignments', [
            'course_id' => $this->course->id,
            'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create()->id,
            'room_id' => Room::factory()->shared()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('room_id');
    }

    public function test_program_admin_sees_and_changes_only_own_assignments(): void
    {
        $own = CourseLecturer::factory()->create(['course_id' => $this->course->id]);
        $other = CourseLecturer::factory()->create();
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getJson('/api/v1/teaching-assignments')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/teaching-assignments/{$other->id}", ['lecturer_id' => Lecturer::factory()->create()->id])->assertForbidden();

        $this->patchJson("/api/v1/teaching-assignments/{$own->id}", ['course_id' => Course::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');

        $this->deleteJson("/api/v1/teaching-assignments/{$own->id}")->assertNoContent();
        $this->assertModelMissing($own);
    }
}
