<?php

namespace Tests\Feature\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * Scope and lock rules module 2 controllers rely on through Gate::authorize().
 */
class MasterDataPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_faculty_admin_manages_only_own_faculty_rooms(): void
    {
        $faculty = Faculty::factory()->create();
        $admin = User::factory()->facultyAdmin($faculty)->create();
        $own = Room::factory()->create(['faculty_id' => $faculty->id]);
        $other = Room::factory()->create();
        $shared = Room::factory()->shared()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $own));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $other));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $shared));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $shared));
        $this->assertTrue(Gate::forUser($admin)->allows('create', [Room::class, $faculty->id]));
        $this->assertFalse(Gate::forUser($admin)->allows('create', [Room::class, $other->faculty_id]));
    }

    public function test_study_program_admin_writes_only_own_program_courses(): void
    {
        $studyProgram = StudyProgram::factory()->create();
        $admin = User::factory()->studyProgramAdmin($studyProgram)->create();

        $this->assertTrue(Gate::forUser($admin)->allows('create', [Course::class, $studyProgram]));
        $this->assertFalse(Gate::forUser($admin)->allows('create', [Course::class, StudyProgram::factory()->create()]));
        $this->assertFalse(Gate::forUser($admin)->allows('view', Course::factory()->create()));
    }

    public function test_submitted_program_is_locked_with_409(): void
    {
        $studyProgram = StudyProgram::factory()->submitted()->create();
        $admin = User::factory()->studyProgramAdmin($studyProgram)->create();
        $course = Course::factory()->create(['study_program_id' => $studyProgram->id]);

        $response = Gate::forUser($admin)->inspect('update', $course);

        $this->assertTrue($response->denied());
        $this->assertSame(409, $response->status());
        $this->assertSame('CONSTRAINTS_LOCKED', $response->code());
    }

    public function test_faculty_admin_may_change_submitted_programs_of_own_faculty(): void
    {
        $studyProgram = StudyProgram::factory()->submitted()->create();
        $admin = User::factory()->facultyAdmin($studyProgram->faculty)->create();
        $course = Course::factory()->create(['study_program_id' => $studyProgram->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('update', $course));
        $this->assertTrue(Gate::forUser($admin)->allows('create', [Lecturer::class, $studyProgram]));
        $this->assertFalse(Gate::forUser($admin)->allows('update', Course::factory()->create()));
    }

    /**
     * A class may be taught by a lecturer of another faculty, so every admin reads all lecturers.
     */
    public function test_lecturers_are_readable_across_faculties_but_managed_by_homebase(): void
    {
        $faculty = Faculty::factory()->create();
        $ownProgram = StudyProgram::factory()->create(['faculty_id' => $faculty->id]);
        $sibling = StudyProgram::factory()->create(['faculty_id' => $faculty->id]);
        $admin = User::factory()->studyProgramAdmin($ownProgram)->create();
        $siblingLecturer = Lecturer::factory()->create(['study_program_id' => $sibling->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $siblingLecturer));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $siblingLecturer));
        $this->assertTrue(Gate::forUser($admin)->allows('view', Lecturer::factory()->create()));
    }

    public function test_tpb_courses_are_managed_by_tpb_admin_only(): void
    {
        $tpbAdmin = User::factory()->tpbAdmin()->create();
        $programAdmin = User::factory()->studyProgramAdmin()->create();
        $tpbCourse = Course::factory()->tpb()->create();
        $regular = Course::factory()->create();

        $this->assertTrue(Gate::forUser($tpbAdmin)->allows('create', [Course::class, null]));
        $this->assertTrue(Gate::forUser($tpbAdmin)->allows('update', $tpbCourse));
        $this->assertTrue(Gate::forUser($tpbAdmin)->allows('view', $regular));
        $this->assertFalse(Gate::forUser($tpbAdmin)->allows('update', $regular));

        $this->assertTrue(Gate::forUser($programAdmin)->allows('view', $tpbCourse));
        $this->assertFalse(Gate::forUser($programAdmin)->allows('update', $tpbCourse));
        $this->assertFalse(Gate::forUser($programAdmin)->allows('create', [Course::class, null]));
    }

    public function test_superadmin_bypasses_scope(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', Room::factory()->shared()->create()));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', Course::factory()->create()));
    }
}
