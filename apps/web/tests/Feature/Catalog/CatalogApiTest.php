<?php

namespace Tests\Feature\Catalog;

use App\Enums\Scheduling\RunStatus;
use App\Mail\PublishedScheduleMail;
use App\Models\MasterData\AcademicPeriod;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private StudyProgram $program;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        config(['scheduling.current_period' => self::PERIOD]);
        AcademicPeriod::factory()->create(['code' => self::PERIOD, 'start_date' => '2026-08-31', 'end_date' => '2026-12-18']);

        $this->faculty = Faculty::factory()->create(['code' => 'FTI']);
        $this->program = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id, 'code' => 'IF']);
        $this->course = Course::factory()->create([
            'study_program_id' => $this->program->id,
            'code' => 'IF2514101',
            'name' => 'Matematika Diskrit',
            'sks' => 3,
            'semester' => 5,
            'parallel_class_count' => 2,
        ]);
    }

    private function publishRun(): SchedulingRun
    {
        $admin = User::factory()->facultyAdmin($this->faculty)->create();
        $run = SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $this->faculty->id,
            'period' => self::PERIOD,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'is_active' => true,
            'published_at' => now(),
            'triggered_by' => $admin->id,
        ]);
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        ScheduleDetail::query()->create([
            'scheduling_run_id' => $run->id,
            'course_id' => $this->course->id,
            'class_number' => 1,
            'meeting_number' => 1,
            'lecturer_id' => $lecturer->id,
            'time_slot_id' => TimeSlot::factory()->create(['day' => 1, 'session' => 1, 'start_time' => '07:30', 'end_time' => '10:00'])->id,
            'room_id' => null,
        ]);

        return $run;
    }

    public function test_faculties_are_public_and_list_programs(): void
    {
        $this->getJson('/api/public/faculties')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'FTI')
            ->assertJsonPath('data.0.study_programs.0.code', 'IF')
            ->assertJsonMissingPath('data.0.can_update');
    }

    public function test_courses_list_classes_with_lecturer_and_hide_internal_fields(): void
    {
        $lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id, 'name' => 'Budi Santoso']);
        CourseLecturer::factory()->create(['course_id' => $this->course->id, 'class_number' => 2, 'lecturer_id' => $lecturer->id]);

        $this->getJson('/api/public/courses')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'IF2514101')
            ->assertJsonPath('data.0.faculty.code', 'FTI')
            ->assertJsonPath('data.0.study_program.code', 'IF')
            ->assertJsonCount(2, 'data.0.classes')
            ->assertJsonPath('data.0.classes.0.class_label', 'A')
            ->assertJsonPath('data.0.classes.0.lecturer', null)
            ->assertJsonPath('data.0.classes.1.class_label', 'B')
            ->assertJsonPath('data.0.classes.1.lecturer.name', 'Budi Santoso')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonMissingPath('data.0.class_capacity')
            ->assertJsonMissingPath('data.0.can_update')
            ->assertJsonMissingPath('data.0.classes.1.lecturer.nip');
    }

    public function test_courses_skip_inactive_and_filter(): void
    {
        Course::factory()->create(['study_program_id' => $this->program->id, 'semester' => 5, 'is_active' => false]);
        $other = StudyProgram::factory()->create(['faculty_id' => Faculty::factory()->create()->id]);
        Course::factory()->create(['study_program_id' => $other->id, 'name' => 'Kalkulus', 'semester' => 1]);

        $this->getJson('/api/public/courses')->assertOk()->assertJsonPath('meta.pagination.total', 2);
        $this->getJson('/api/public/courses?faculty_id='.$this->faculty->id)->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->getJson('/api/public/courses?study_program_id='.$other->id)->assertOk()->assertJsonPath('data.0.name', 'Kalkulus');
        $this->getJson('/api/public/courses?semester=5')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->getJson('/api/public/courses?q=diskrit')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->getJson('/api/public/courses?per_page=500')->assertUnprocessable();
    }

    public function test_tpb_courses_have_no_program_or_faculty(): void
    {
        Course::factory()->tpb()->create(['code' => 'UM1001', 'name' => 'Pancasila']);

        $this->getJson('/api/public/courses?q=Pancasila')
            ->assertOk()
            ->assertJsonPath('data.0.is_tpb', true)
            ->assertJsonPath('data.0.study_program', null)
            ->assertJsonPath('data.0.faculty', null);
    }

    public function test_email_sends_published_schedule_in_every_chosen_format(): void
    {
        Mail::fake();
        $this->publishRun();

        $this->postJson('/api/public/schedule-email', [
            'email' => 'mhs@student.itk.ac.id',
            'classes' => [$this->course->id.':1'],
            'formats' => ['xlsx', 'ics'],
        ])->assertStatus(202)->assertJsonPath('data.queued', true);

        Mail::assertSent(PublishedScheduleMail::class, function (PublishedScheduleMail $mail) {
            $names = array_column($mail->files, 'filename');

            return $mail->hasTo('mhs@student.itk.ac.id')
                && count($names) === 2
                && str_ends_with($names[0], '.xlsx')
                && str_ends_with($names[1], '.ics')
                && str_contains($mail->files[1]['content'], 'BEGIN:VCALENDAR')
                && str_contains($mail->render(), 'Ganjil 2026/2027');
        });
    }

    public function test_email_is_refused_while_schedule_is_unpublished(): void
    {
        Mail::fake();
        $run = $this->publishRun();
        $run->update(['published_at' => null]);

        $this->postJson('/api/public/schedule-email', [
            'email' => 'mhs@student.itk.ac.id',
            'classes' => [(string) $this->course->id],
            'formats' => ['pdf'],
        ])->assertUnprocessable()->assertJsonValidationErrorFor('classes', 'errors');

        Mail::assertNothingSent();
    }

    public function test_email_only_includes_the_picked_classes(): void
    {
        Mail::fake();
        $this->publishRun();

        // Class B of the course has no meeting in the published run.
        $this->postJson('/api/public/schedule-email', [
            'email' => 'mhs@student.itk.ac.id',
            'classes' => [$this->course->id.':2'],
            'formats' => ['xlsx'],
        ])->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_email_validates_input(): void
    {
        $this->postJson('/api/public/schedule-email', ['email' => 'not-an-email', 'classes' => ['abc'], 'formats' => ['docx']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'classes.0', 'formats.0'], 'errors');

        $this->postJson('/api/public/schedule-email', [])->assertUnprocessable();
    }

    public function test_email_is_throttled_per_address(): void
    {
        Mail::fake();
        $this->publishRun();
        $payload = ['email' => 'mhs@student.itk.ac.id', 'classes' => [$this->course->id.':1'], 'formats' => ['ics']];

        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/public/schedule-email', $payload)->assertStatus(202);
        }

        $this->postJson('/api/public/schedule-email', $payload)->assertStatus(429);
    }
}
