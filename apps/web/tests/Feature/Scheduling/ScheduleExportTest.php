<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\AcademicPeriod;
use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ScheduleExportTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private StudyProgram $program;

    private User $admin;

    private Lecturer $lecturer;

    private Lecturer $otherLecturer;

    private SchedulingRun $run;

    private Course $algo;

    private Course $basis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        config(['scheduling.current_period' => self::PERIOD]);
        // 2026-08-31 is a Monday.
        AcademicPeriod::factory()->create(['code' => self::PERIOD, 'start_date' => '2026-08-31', 'end_date' => '2026-12-18']);

        $faculty = Faculty::factory()->create();
        $this->program = StudyProgram::factory()->accepted()->create(['faculty_id' => $faculty->id, 'name' => 'Informatika']);
        $this->admin = User::factory()->facultyAdmin($faculty)->create();
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id, 'name' => 'Budi Santoso']);
        $this->otherLecturer = Lecturer::factory()->create(['study_program_id' => $this->program->id]);
        $this->algo = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF201', 'name' => 'Algoritma, Struktur Data', 'sks' => 3, 'semester' => 3, 'parallel_class_count' => 2]);
        $this->basis = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF202', 'name' => 'Basis Data', 'sks' => 3, 'semester' => 3]);

        $this->run = SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $faculty->id,
            'period' => self::PERIOD,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'is_active' => true,
            'triggered_by' => $this->admin->id,
        ]);

        $room = Room::factory()->create(['code' => 'G-201']);
        $monday = TimeSlot::factory()->create(['day' => 1, 'session' => 1, 'start_time' => '07:30', 'end_time' => '10:00']);
        $wednesday = TimeSlot::factory()->create(['day' => 3, 'session' => 2, 'start_time' => '10:20', 'end_time' => '12:50']);
        $this->gene($this->algo, 1, $this->lecturer, $monday, $room->id);
        $this->gene($this->algo, 2, $this->otherLecturer, $wednesday, $room->id);
        $this->gene($this->basis, 1, $this->otherLecturer, $monday, null);

        Sanctum::actingAs($this->admin);
    }

    private function gene(Course $course, int $class, Lecturer $lecturer, TimeSlot $slot, ?int $roomId): void
    {
        ScheduleDetail::query()->create([
            'scheduling_run_id' => $this->run->id,
            'course_id' => $course->id,
            'class_number' => $class,
            'meeting_number' => 1,
            'lecturer_id' => $lecturer->id,
            'time_slot_id' => $slot->id,
            'room_id' => $roomId,
        ]);
    }

    private function export(string $query): TestResponse
    {
        return $this->get('/api/v1/schedules/export?'.$query, ['Accept' => 'application/json']);
    }

    public function test_xlsx_has_matrix_and_list_sheets(): void
    {
        $response = $this->export('format=xlsx&study_program_id='.$this->program->id)->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('jadwal-'.Str::slug($this->program->code).'-2026-2027-ganjil.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->getContent());
        $book = IOFactory::load($path);
        unlink($path);

        $this->assertSame(['Matriks', 'Daftar'], $book->getSheetNames());
        $list = $book->getSheetByName('Daftar');
        // Title, subtitle, printed, blank, then the header on row 6 because the run is a draft.
        $this->assertSame('DRAF — belum dipublikasikan', $list->getCell('A4')->getValue());
        $this->assertSame('Hari', $list->getCell('A6')->getValue());
        $this->assertSame(9, $list->getHighestDataRow());
        $this->assertStringContainsString('IF201 Algoritma, Struktur Data (A)', $book->getSheetByName('Matriks')->getCell('B7')->getValue());
    }

    public function test_lecturer_filter_narrows_and_names_the_file(): void
    {
        $this->export("format=xlsx&lecturer_id={$this->lecturer->id}")->assertOk()
            ->assertDownload('jadwal-dosen-budi-santoso-2026-2027-ganjil.xlsx');
    }

    public function test_ics_repeats_each_meeting_weekly_from_the_period_dates(): void
    {
        $ics = $this->export('format=ics')->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART;TZID=Asia/Makassar:20260831T073000', $ics);
        $this->assertStringContainsString('DTSTART;TZID=Asia/Makassar:20260902T102000', $ics);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;UNTIL=', $ics);
        $this->assertStringContainsString('SUMMARY:IF201 Algoritma\, Struktur Data (A)', $ics);
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
    }

    public function test_ics_needs_period_dates_but_xlsx_does_not(): void
    {
        AcademicPeriod::query()->delete();

        $this->export('format=ics')->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
        $this->export('format=xlsx')->assertOk();
    }

    public function test_pdf_is_printed_by_gotenberg(): void
    {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $this->export('format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        Http::assertSent(fn (Request $request) => str_contains($request->body(), 'Basis Data')
            && str_contains($request->body(), 'DRAF')
            && str_contains($request->body(), 'name="paperWidth"'));
    }

    public function test_pdf_reports_gotenberg_outage(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->export('format=pdf')->assertStatus(503)->assertJsonPath('code', 'PDF_UNAVAILABLE');
    }

    public function test_picked_classes_keep_only_those_classes(): void
    {
        $ics = $this->export("format=ics&classes[]={$this->algo->id}:2&classes[]={$this->basis->id}")->assertOk()->getContent();
        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('(B)', $ics);
        $this->assertStringNotContainsString('Struktur Data (A)', $ics);

        $this->getJson("/api/v1/schedules?classes[]={$this->algo->id}:1")->assertOk()->assertJsonCount(1, 'data.schedule_details');
        $this->export('format=ics&classes[]=abc')->assertUnprocessable()->assertJsonValidationErrors('classes.0');
    }

    public function test_student_cannot_export_an_unpublished_schedule(): void
    {
        Sanctum::actingAs(User::factory()->student($this->program)->create());
        $this->export('format=xlsx')->assertStatus(409);

        $this->run->update(['published_at' => now()]);
        $this->export("format=ics&study_program_id={$this->program->id}&semester=3&class_number=1")->assertOk();
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->export('format=docx')->assertUnprocessable()->assertJsonValidationErrors('format');
    }
}
