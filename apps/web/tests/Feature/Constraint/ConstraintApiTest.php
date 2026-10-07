<?php

namespace Tests\Feature\Constraint;

use App\Enums\Constraint\ConstraintStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ConstraintApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Faculty $faculty;

    private StudyProgram $program;

    private User $prodiAdmin;

    private User $facultyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);

        $this->faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->prodiAdmin = User::factory()->studyProgramAdmin($this->program)->create();
        $this->facultyAdmin = User::factory()->facultyAdmin($this->faculty)->create();
    }

    /**
     * @return list<TimeSlot>
     */
    private function slots(int $count): array
    {
        return array_map(fn (int $session) => TimeSlot::factory()->create(['session' => $session]), range(1, $count));
    }

    /** A course whose every class has a lecturer. */
    private function completeCourse(StudyProgram $program): Course
    {
        $course = Course::factory()->create(['study_program_id' => $program->id, 'parallel_class_count' => 1]);
        CourseLecturer::factory()->create([
            'course_id' => $course->id,
            'class_number' => 1,
            'lecturer_id' => Lecturer::factory()->create(['study_program_id' => $program->id])->id,
        ]);

        return $course;
    }

    private function submittedProgram(): StudyProgram
    {
        $program = StudyProgram::factory()->submitted()->create(['faculty_id' => $this->faculty->id]);
        $this->completeCourse($program);

        return $program;
    }

    // weights

    public function test_weights_are_listed_and_only_soft_ones_can_change(): void
    {
        $this->actingAs($this->prodiAdmin)->getJson('/api/internal/constraint-types')
            ->assertOk()->assertJsonCount(7, 'data');

        $soft = ConstraintType::query()->where('code', 'SC_SKS')->firstOrFail();
        $hard = ConstraintType::query()->where('code', 'HC1')->firstOrFail();

        $this->actingAs($this->facultyAdmin)->patchJson("/api/internal/constraint-types/{$soft->id}", ['weight' => 5])
            ->assertOk()->assertJsonPath('data.weight', 5);
        $this->assertSame('5.00', (string) $soft->fresh()->weight);

        $this->patchJson("/api/internal/constraint-types/{$hard->id}", ['weight' => 5])->assertForbidden();
        $this->patchJson("/api/internal/constraint-types/{$soft->id}", ['weight' => 101])->assertUnprocessable();
        $this->patchJson("/api/internal/constraint-types/{$soft->id}", ['weight' => -1])->assertUnprocessable();

        $this->actingAs($this->prodiAdmin)->patchJson("/api/internal/constraint-types/{$soft->id}", ['weight' => 3])->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['event' => 'constraint.weight_changed']);
    }

    // preferences

    public function test_prodi_admin_replaces_the_preference_matrix_of_own_lecturer(): void
    {
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        [$a, $b, $c] = $this->slots(3);

        $this->actingAs($this->prodiAdmin)->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", [
            'preferences' => [
                ['time_slot_id' => $a->id, 'type' => 'want'],
                ['time_slot_id' => $b->id, 'type' => 'avoid'],
            ],
        ])->assertOk()->assertJsonCount(2, 'data.preferences');

        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", [
            'preferences' => [['time_slot_id' => $c->id, 'type' => 'avoid']],
        ])->assertOk()->assertJsonPath('data.preferences.0.time_slot_id', $c->id);

        $this->assertSame(1, LecturerPreference::query()->where('lecturer_id', $lecturer->id)->count());
        $this->getJson("/api/internal/lecturers/{$lecturer->id}/preferences")
            ->assertOk()->assertJsonPath('data.preferences.0.type', 'avoid');

        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", ['preferences' => []])
            ->assertOk()->assertJsonCount(0, 'data.preferences');
        $this->assertDatabaseHas('activity_logs', ['event' => 'preference.replaced']);
    }

    public function test_preference_input_is_validated(): void
    {
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $slot = $this->slots(1)[0];
        $this->actingAs($this->prodiAdmin);

        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", [])->assertUnprocessable();
        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", ['preferences' => [['time_slot_id' => 9999, 'type' => 'want']]])->assertUnprocessable();
        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", ['preferences' => [['time_slot_id' => $slot->id, 'type' => 'maybe']]])->assertUnprocessable();
        $this->putJson("/api/internal/lecturers/{$lecturer->id}/preferences", ['preferences' => [
            ['time_slot_id' => $slot->id, 'type' => 'want'],
            ['time_slot_id' => $slot->id, 'type' => 'avoid'],
        ]])->assertUnprocessable();
    }

    public function test_preferences_are_refused_for_other_programs_roles_and_locked_programs(): void
    {
        $slot = $this->slots(1)[0];
        $body = ['preferences' => [['time_slot_id' => $slot->id, 'type' => 'want']]];
        $foreign = Lecturer::factory()->create();
        $own = Lecturer::factory()->create(['study_program_id' => $this->program->id]);

        $this->actingAs($this->prodiAdmin)->putJson("/api/internal/lecturers/{$foreign->id}/preferences", $body)->assertForbidden();
        $this->actingAs($this->facultyAdmin)->putJson("/api/internal/lecturers/{$own->id}/preferences", $body)->assertForbidden();
        $this->actingAs(User::factory()->student()->create())->getJson("/api/internal/lecturers/{$own->id}/preferences")->assertForbidden();

        $this->program->update(['constraint_status' => ConstraintStatus::SUBMITTED]);
        $this->actingAs($this->prodiAdmin)->putJson("/api/internal/lecturers/{$own->id}/preferences", $body)
            ->assertStatus(409)->assertJsonPath('code', 'CONSTRAINTS_LOCKED');

        $this->program->update(['constraint_status' => ConstraintStatus::ACCEPTED]);
        $this->putJson("/api/internal/lecturers/{$own->id}/preferences", $body)->assertStatus(409);
    }

    // submit

    public function test_submit_needs_every_class_to_have_a_lecturer(): void
    {
        $this->actingAs($this->prodiAdmin);

        $this->postJson("/api/internal/constraint-submissions/{$this->program->id}/submit")
            ->assertUnprocessable()->assertJsonPath('errors.courses.0', 'Program studi belum memiliki mata kuliah.');

        $this->completeCourse($this->program);
        $missing = Course::factory()->create(['study_program_id' => $this->program->id, 'parallel_class_count' => 2, 'code' => 'XX9999']);

        $this->postJson("/api/internal/constraint-submissions/{$this->program->id}/submit")
            ->assertUnprocessable()->assertJsonFragment(['Mata kuliah XX9999 baru memiliki dosen di 0 dari 2 kelas.']);
        $this->assertSame(ConstraintStatus::DRAFT, $this->program->fresh()->constraint_status);

        $missing->delete();
        $this->postJson("/api/internal/constraint-submissions/{$this->program->id}/submit")
            ->assertOk()->assertJsonPath('data.constraint_status', 'submitted')->assertJsonPath('data.is_locked', true);

        $program = $this->program->fresh();
        $this->assertNotNull($program->constraint_submitted_at);
        $this->assertTrue($program->isLocked());
        $this->assertDatabaseHas('activity_logs', ['event' => 'constraint.submitted']);

        $this->postJson("/api/internal/constraint-submissions/{$this->program->id}/submit")
            ->assertStatus(409)->assertJsonPath('code', 'CONSTRAINTS_LOCKED');
    }

    public function test_only_the_own_prodi_admin_submits(): void
    {
        $this->completeCourse($this->program);
        $other = User::factory()->studyProgramAdmin()->create();

        $this->actingAs($other)->postJson("/api/internal/constraint-submissions/{$this->program->id}/submit")->assertForbidden();
        $this->actingAs($this->facultyAdmin)->postJson("/api/internal/constraint-submissions/{$this->program->id}/submit")->assertForbidden();
    }

    // review

    public function test_faculty_admin_accepts_a_submitted_program(): void
    {
        $program = $this->submittedProgram();

        $this->actingAs($this->facultyAdmin)->postJson("/api/internal/constraint-submissions/{$program->id}/accept")
            ->assertOk()->assertJsonPath('data.constraint_status', 'accepted');

        $program->refresh();
        $this->assertSame($this->facultyAdmin->id, (int) $program->constraint_reviewed_by);
        $this->assertNotNull($program->constraint_reviewed_at);
        $this->assertTrue($program->isLocked());

        $this->postJson("/api/internal/constraint-submissions/{$program->id}/accept")
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
        $this->assertDatabaseHas('activity_logs', ['event' => 'constraint.accepted']);
    }

    public function test_faculty_admin_returns_a_program_with_a_note(): void
    {
        $program = $this->submittedProgram();
        $this->actingAs($this->facultyAdmin);

        $this->postJson("/api/internal/constraint-submissions/{$program->id}/return", [])->assertUnprocessable();
        $this->postJson("/api/internal/constraint-submissions/{$program->id}/return", ['note' => 'Lengkapi dosen IF2101'])
            ->assertOk()
            ->assertJsonPath('data.constraint_status', 'draft')
            ->assertJsonPath('data.constraint_return_note', 'Lengkapi dosen IF2101');

        $program->refresh();
        $this->assertNull($program->constraint_submitted_at);
        $this->assertFalse($program->isLocked());
        $this->postJson("/api/internal/constraint-submissions/{$program->id}/return", ['note' => 'x'])->assertStatus(409);

        $accepted = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id, 'constraint_status' => ConstraintStatus::ACCEPTED]);
        $this->postJson("/api/internal/constraint-submissions/{$accepted->id}/return", ['note' => 'Revisi'])
            ->assertOk()->assertJsonPath('data.constraint_status', 'draft')->assertJsonPath('data.is_locked', false);
        $this->assertDatabaseHas('activity_logs', ['event' => 'constraint.returned']);
    }

    public function test_review_is_refused_for_prodi_admins_and_other_faculties(): void
    {
        $program = $this->submittedProgram();
        $otherFacultyAdmin = User::factory()->facultyAdmin()->create();

        $this->actingAs($this->prodiAdmin)->postJson("/api/internal/constraint-submissions/{$program->id}/accept")->assertForbidden();
        $this->actingAs($otherFacultyAdmin)->postJson("/api/internal/constraint-submissions/{$program->id}/accept")->assertForbidden();
        $this->actingAs($otherFacultyAdmin)->postJson("/api/internal/constraint-submissions/{$program->id}/return", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->facultyAdmin)->postJson("/api/internal/constraint-submissions/{$this->program->id}/accept")
            ->assertStatus(409);
    }

    // list and detail

    public function test_faculty_admin_lists_only_own_faculty_with_search_and_status_filters(): void
    {
        $submitted = $this->submittedProgram();
        StudyProgram::factory()->submitted()->create();

        $this->actingAs($this->facultyAdmin);

        $this->getJson('/api/internal/constraint-submissions')
            ->assertOk()->assertJsonPath('meta.pagination.total', 2);
        $this->getJson('/api/internal/constraint-submissions?status=submitted')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $submitted->id);
        $this->getJson('/api/internal/constraint-submissions?status=nonsense')->assertUnprocessable();
        $this->getJson('/api/internal/constraint-submissions?q='.urlencode($this->program->code))
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $this->program->id);
    }

    public function test_prodi_admin_lists_only_the_own_program(): void
    {
        StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);

        $this->actingAs($this->prodiAdmin)->getJson('/api/internal/constraint-submissions')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $this->program->id);
    }

    public function test_list_row_counts_lecturers_preferences_and_incomplete_courses(): void
    {
        $withPreference = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        LecturerPreference::query()->create([
            'lecturer_id' => $withPreference->id,
            'constraint_type_id' => ConstraintType::query()->where('code', 'SC_INGIN')->value('id'),
            'time_slot_id' => $this->slots(1)[0]->id,
        ]);
        $this->completeCourse($this->program);
        Course::factory()->create(['study_program_id' => $this->program->id, 'parallel_class_count' => 2]);

        $this->actingAs($this->prodiAdmin)->getJson('/api/internal/constraint-submissions')
            ->assertOk()
            ->assertJsonPath('data.0.lecturers_count', 3)
            ->assertJsonPath('data.0.lecturers_with_preferences_count', 1)
            ->assertJsonPath('data.0.courses_count', 2)
            ->assertJsonPath('data.0.incomplete_courses_count', 1)
            ->assertJsonPath('data.0.can_submit', true)
            ->assertJsonPath('data.0.can_review', false);
    }

    public function test_detail_lists_incomplete_courses_and_lecturer_preferences(): void
    {
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $slot = $this->slots(1)[0];
        LecturerPreference::query()->create([
            'lecturer_id' => $lecturer->id,
            'constraint_type_id' => ConstraintType::query()->where('code', 'SC_HINDARI')->value('id'),
            'time_slot_id' => $slot->id,
        ]);
        $course = Course::factory()->create(['study_program_id' => $this->program->id, 'parallel_class_count' => 2]);

        $this->actingAs($this->facultyAdmin)->getJson("/api/internal/constraint-submissions/{$this->program->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.id', $this->program->id)
            ->assertJsonPath('data.incomplete_courses.0.id', $course->id)
            ->assertJsonPath('data.incomplete_courses.0.assigned_class_count', 0)
            ->assertJsonPath('data.lecturers.0.preferences.0.type', 'avoid');

        $this->actingAs(User::factory()->facultyAdmin()->create())
            ->getJson("/api/internal/constraint-submissions/{$this->program->id}")->assertForbidden();
    }

    public function test_other_roles_are_refused_everywhere(): void
    {
        $this->actingAs(User::factory()->student($this->program)->create());

        $this->getJson('/api/internal/constraint-submissions')->assertForbidden();
        $this->getJson('/api/internal/constraint-types')->assertForbidden();
        $this->getJson('/api/internal/constraint-submissions/'.$this->program->id)->assertForbidden();
    }

    public function test_the_last_time_slot_cannot_be_deleted(): void
    {
        $slot = $this->slots(1)[0];

        $this->actingAs(User::factory()->superAdmin()->create())->deleteJson("/api/internal/time-slots/{$slot->id}")
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
    }
}
