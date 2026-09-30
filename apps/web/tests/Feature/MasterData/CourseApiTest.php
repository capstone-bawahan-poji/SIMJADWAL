<?php

namespace Tests\Feature\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private StudyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->program = StudyProgram::factory()->create();
    }

    public function test_program_admin_creates_regular_course(): void
    {
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->postJson('/api/v1/courses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_tpb', false)
            ->assertJsonPath('data.study_program_id', $this->program->id)
            ->assertJsonPath('data.course_lecturers_count', 0);
    }

    public function test_only_tpb_admin_creates_tpb_course_without_program(): void
    {
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());
        $this->postJson('/api/v1/courses', $this->payload(['is_tpb' => true, 'study_program_id' => null, 'code' => 'KU2511014']))
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->postJson('/api/v1/courses', $this->payload(['is_tpb' => true, 'study_program_id' => $this->program->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('study_program_id');

        $this->postJson('/api/v1/courses', $this->payload(['is_tpb' => true, 'study_program_id' => null, 'code' => 'KU2511014']))
            ->assertCreated()
            ->assertJsonPath('data.is_tpb', true)
            ->assertJsonPath('data.study_program', null);
    }

    public function test_list_shows_own_program_and_all_tpb_courses(): void
    {
        Course::factory()->create(['study_program_id' => $this->program->id]);
        Course::factory()->create();
        Course::factory()->tpb()->create();
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->getJson('/api/v1/courses')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/courses?is_tpb=1')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->getJson('/api/v1/courses')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_owner_cannot_change(): void
    {
        $course = Course::factory()->create(['study_program_id' => $this->program->id]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->patchJson("/api/v1/courses/{$course->id}", ['study_program_id' => StudyProgram::factory()->create()->id, 'is_tpb' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['study_program_id', 'is_tpb']);

        $this->patchJson("/api/v1/courses/{$course->id}", ['study_program_id' => $this->program->id, 'is_tpb' => false, 'name' => 'Form Lengkap'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Form Lengkap');
    }

    public function test_lowering_class_count_keeps_mapped_classes(): void
    {
        $course = Course::factory()->create(['study_program_id' => $this->program->id, 'parallel_class_count' => 4]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 3]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 4]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->patchJson("/api/v1/courses/{$course->id}", ['parallel_class_count' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('errors.parallel_class_count.0', 'Kelas C, D masih punya dosen pengampu. Hapus penugasan mengajar kelas tersebut terlebih dahulu.');

        $this->patchJson("/api/v1/courses/{$course->id}", ['parallel_class_count' => 4, 'name' => 'Nama Baru'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_delete_is_refused_while_mapped_or_grouped(): void
    {
        $course = Course::factory()->create(['study_program_id' => $this->program->id]);
        $assignment = CourseLecturer::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());

        $this->deleteJson("/api/v1/courses/{$course->id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.teaching_assignments', 1);

        $assignment->delete();
        $this->deleteJson("/api/v1/courses/{$course->id}")->assertNoContent();
        $this->assertSoftDeleted($course);

        $tpb = Course::factory()->tpb()->create();
        TpbGroup::factory()->create(['course_id' => $tpb->id]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->deleteJson("/api/v1/courses/{$tpb->id}")
            ->assertStatus(409)
            ->assertJsonPath('errors.references.tpb_groups', 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'study_program_id' => $this->program->id,
            'code' => 'IF2514201',
            'name' => 'Aljabar Linier dan Geometri',
            'sks' => 3,
            'semester' => 1,
            'parallel_class_count' => 2,
            'class_capacity' => 40,
            ...$overrides,
        ];
    }
}
