<?php

namespace Tests\Feature\Web;

use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Faculty $faculty;

    private StudyProgram $program;

    private User $facultyAdmin;

    private User $programAdmin;

    private Course $fourSks;

    private Lecturer $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        config(['scheduling.current_period' => '2026/2027-GANJIL']);

        $this->faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id]);
        StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->facultyAdmin = User::factory()->facultyAdmin($this->faculty)->create();
        $this->programAdmin = User::factory()->studyProgramAdmin($this->program)->create();
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->fourSks = Course::factory()->create(['study_program_id' => $this->program->id, 'sks' => 4, 'semester' => 3, 'class_capacity' => 40, 'parallel_class_count' => 1]);
        CourseLecturer::factory()->create(['course_id' => $this->fourSks->id, 'class_number' => 1, 'lecturer_id' => $this->lecturer->id]);
    }

    private function activeRun(bool $published): SchedulingRun
    {
        $run = SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $this->faculty->id,
            'period' => '2026/2027-GANJIL',
            'parameter_snapshot' => ['parallel_class_limit' => 5],
            'status' => RunStatus::DONE,
            'is_active' => true,
            'best_fitness' => 490,
            'max_fitness' => 500,
            'triggered_by' => $this->facultyAdmin->id,
            'published_at' => $published ? now() : null,
        ]);

        $small = Room::factory()->create(['faculty_id' => null, 'capacity' => 10]);
        foreach ([[1, null], [2, $small->id]] as [$meeting, $roomId]) {
            ScheduleDetail::query()->create([
                'scheduling_run_id' => $run->id,
                'course_id' => $this->fourSks->id,
                'class_number' => 1,
                'meeting_number' => $meeting,
                'lecturer_id' => $this->lecturer->id,
                'time_slot_id' => TimeSlot::factory()->create(['day' => $meeting, 'session' => 1])->id,
                'room_id' => $roomId,
            ]);
        }

        return $run;
    }

    public function test_faculty_dashboard_reports_constraints_active_run_and_room_issues(): void
    {
        $run = $this->activeRun(published: false);

        $this->actingAs($this->facultyAdmin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Fakultas/Dashboard')
                ->where('dashboard.period', '2026/2027-GANJIL')
                ->where('dashboard.constraints.total', 2)
                ->where('dashboard.constraints.accepted', 1)
                ->where('dashboard.active_run.id', $run->id)
                ->where('dashboard.active_run.published_at', null)
                ->where('dashboard.active_run.schedule_details_count', 2)
                ->where('dashboard.rooms.unassigned_count', 1)
                ->where('dashboard.rooms.over_capacity_count', 1)
                ->has('dashboard.rooms.issues', 2)
                ->where('dashboard.rooms.issues.0.type', 'unassigned')
                ->where('dashboard.rooms.issues.0.class_label', 'A'));
    }

    public function test_faculty_dashboard_without_active_run(): void
    {
        $this->actingAs($this->facultyAdmin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.active_run', null)
                ->where('dashboard.rooms.issues', []));
    }

    public function test_program_dashboard_hides_numbers_until_published(): void
    {
        $this->actingAs($this->programAdmin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Prodi/Dashboard')
                ->where('dashboard.schedule.status', 'none')
                ->where('dashboard.constraint.courses_count', 1)
                ->where('dashboard.constraint.incomplete_courses_count', 0)
                ->where('dashboard.constraint.completeness_percent', 50));

        $run = $this->activeRun(published: false);
        $this->actingAs($this->programAdmin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.schedule.status', 'active')
                ->where('dashboard.schedule.classes_count', null));

        $run->update(['published_at' => now()]);
        $this->actingAs($this->programAdmin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.schedule.status', 'published')
                ->where('dashboard.schedule.classes_count', 1)
                ->where('dashboard.schedule.sks_total', 4)
                ->where('dashboard.schedule.unassigned_rooms', 1));
    }

    public function test_superadmin_still_gets_stats(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('stats.faculties'));
    }
}