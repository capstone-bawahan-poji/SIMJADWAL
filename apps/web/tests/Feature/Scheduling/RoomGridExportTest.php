<?php

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\RunStatus;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class RoomGridExportTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Faculty $faculty;

    private Room $room;

    private TimeSlot $monday;

    private Lecturer $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        config(['scheduling.current_period' => self::PERIOD]);

        $this->faculty = Faculty::factory()->create(['code' => 'FSTI']);
        $program = StudyProgram::factory()->accepted()->create(['faculty_id' => $this->faculty->id, 'code' => 'IF', 'name' => 'Informatika']);
        $this->lecturer = Lecturer::factory()->create(['study_program_id' => $program->id, 'name' => 'Budi Santoso']);
        $this->room = Room::factory()->create(['code' => 'E101', 'building' => 'E', 'faculty_id' => null]);
        $this->monday = TimeSlot::factory()->create(['day' => 1, 'session' => 1, 'start_time' => '07:30', 'end_time' => '10:00']);

        $facultyRun = $this->makeRun($this->faculty->id, published: true);
        $tpbRun = $this->makeRun(null, published: true);

        $facultyCourse = Course::factory()->create(['study_program_id' => $program->id, 'code' => 'IF201', 'name' => 'Algoritma', 'semester' => 3]);
        $tpbCourse = Course::factory()->create(['study_program_id' => null, 'is_tpb' => true, 'code' => 'KU1', 'name' => 'Matematika 1', 'semester' => null, 'parallel_class_count' => null, 'class_capacity' => null]);
        $this->gene($facultyRun, $facultyCourse, $this->monday);
        $this->gene($tpbRun, $tpbCourse, $this->monday);
    }

    private function makeRun(?int $facultyId, bool $published): SchedulingRun
    {
        return SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $facultyId,
            'period' => self::PERIOD,
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'is_active' => true,
            'published_at' => $published ? now() : null,
            'triggered_by' => User::factory()->create()->id,
        ]);
    }

    private function gene(SchedulingRun $run, Course $course, TimeSlot $slot): void
    {
        ScheduleDetail::query()->create([
            'scheduling_run_id' => $run->id,
            'course_id' => $course->id,
            'class_number' => 1,
            'meeting_number' => 1,
            'lecturer_id' => $this->lecturer->id,
            'time_slot_id' => $slot->id,
            'room_id' => $this->room->id,
        ]);
    }

    private function export(string $query = 'format=xlsx'): TestResponse
    {
        return $this->get('/api/internal/schedules/export-room-grid?'.$query, ['Accept' => 'application/json']);
    }

    private function book(TestResponse $response): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->getContent());
        $book = IOFactory::load($path);
        unlink($path);

        return $book;
    }

    public function test_superadmin_export_is_coloured_by_group_with_the_group_code_and_a_red_clash_frame(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $book = $this->book($this->export()->assertOk()->assertDownload('seluruh-jadwal-tpb-fakultas-2026-2027-ganjil.xlsx'));

        $this->assertSame(['Ruangan'], $book->getSheetNames());
        $grid = $book->getSheetByName('Ruangan');
        $this->assertSame('Seluruh jadwal TPB dan fakultas', $grid->getCell('A1')->getValue());
        $this->assertSame('GEDUNG E', $grid->getCell('C6')->getValue());
        $this->assertSame('E101', $grid->getCell('C7')->getValue());
        $this->assertSame(['TPB', 'FSTI', 'FPB', 'FRTI'], [$grid->getCell('C4')->getValue(), $grid->getCell('D4')->getValue(), $grid->getCell('E4')->getValue(), $grid->getCell('F4')->getValue()]);
        // The two genes share room and slot (and lecturer): 2 clashes, a red frame, group codes in the cell.
        $this->assertSame('2 tabrakan', $grid->getCell('A5')->getValue());
        $cell = $grid->getCell('C9')->getValue();
        $this->assertStringContainsString('Algoritma', $cell);
        $this->assertStringContainsString('Matematika 1', $cell);
        $this->assertStringContainsString("\nFSTI", $cell);
        $this->assertStringContainsString("\nTPB", $cell);
        $this->assertSame('FFDC2626', $grid->getStyle('C9')->getBorders()->getLeft()->getColor()->getARGB());
    }

    public function test_superadmin_picking_tpb_gets_the_tpb_group_colour_and_no_clash_badge_trouble(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $grid = $this->book($this->export('format=xlsx&scope=tpb')->assertOk()->assertDownload('seluruh-jadwal-tpb-2026-2027-ganjil.xlsx'))->getSheetByName('Ruangan');

        $this->assertSame('Tanpa tabrakan', $grid->getCell('A5')->getValue());
        $this->assertSame('FFC8D6EA', $grid->getStyle('C9')->getFill()->getStartColor()->getARGB());
        $this->assertStringNotContainsString('Algoritma', $grid->getCell('C9')->getValue());
    }

    public function test_tpb_admin_only_gets_tpb_coloured_per_course(): void
    {
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());

        $grid = $this->book($this->export('format=xlsx&scope=faculty')->assertOk()->assertDownload('seluruh-jadwal-tpb-2026-2027-ganjil.xlsx'))->getSheetByName('Ruangan');

        $this->assertStringContainsString('Matematika 1', $grid->getCell('C9')->getValue());
        $this->assertStringNotContainsString('Algoritma', $grid->getCell('C9')->getValue());
        $this->assertSame('Matematika 1', $grid->getCell('C4')->getValue());
        $this->assertSame('FF97B2D8', $grid->getStyle('C9')->getFill()->getStartColor()->getARGB());
        $this->assertSame('FF2E67B8', $grid->getStyle('C9')->getBorders()->getLeft()->getColor()->getARGB());
        $this->assertStringNotContainsString("\nTPB", $grid->getCell('C9')->getValue());
    }

    public function test_faculty_admin_only_gets_their_own_faculty_coloured_per_study_program(): void
    {
        $other = Faculty::factory()->create();
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $grid = $this->book($this->export("format=xlsx&scope=tpb&faculty_id={$other->id}")->assertOk())->getSheetByName('Ruangan');

        $this->assertStringContainsString('Algoritma', $grid->getCell('C9')->getValue());
        $this->assertStringNotContainsString('Matematika 1', $grid->getCell('C9')->getValue());
        $this->assertSame('Informatika', $grid->getCell('C4')->getValue());
        $this->assertSame('FFB5E3D4', $grid->getStyle('C9')->getFill()->getStartColor()->getARGB());
    }

    public function test_unpublished_schedule_is_not_exported(): void
    {
        SchedulingRun::query()->update(['published_at' => null]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->export()->assertStatus(409)->assertJsonPath('code', 'INVALID_STATE');
    }

    public function test_lecturer_and_study_program_admin_are_refused(): void
    {
        Sanctum::actingAs(User::factory()->lecturer()->create());
        $this->export()->assertForbidden();

        Sanctum::actingAs(User::factory()->studyProgramAdmin()->create());
        $this->export()->assertForbidden();
    }

    public function test_pdf_is_printed_by_gotenberg_with_a_page_per_building_and_the_clash_list(): void
    {
        Http::fake(['*' => Http::response('%PDF-fake', 200)]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->export('format=pdf')->assertOk()->assertDownload('seluruh-jadwal-tpb-fakultas-2026-2027-ganjil.pdf');

        Http::assertSent(function (Request $request) {
            $html = collect($request->data())->firstWhere('name', 'files')['contents'] ?? '';

            return str_contains($request->url(), '/forms/chromium/convert/html')
                && str_contains($html, 'Gedung E')
                && str_contains($html, 'E101')
                && str_contains($html, '2 tabrakan')
                && str_contains($html, '#C8D6EA');
        });
    }

    public function test_room_grid_json_has_the_cells_colours_and_clash_count_of_the_export(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $data = $this->getJson('/api/internal/schedules/room-grid')->assertOk()->json('data');

        $this->assertSame('superadmin', $data['mode']);
        $this->assertSame(2, $data['clash_count']);
        $this->assertSame('E101', $data['buildings'][0]['rooms'][0]['code']);
        $this->assertCount(1, $data['cells']);
        $this->assertTrue($data['cells'][0]['clash']);
        $this->assertSame(['TPB', 'FSTI'], collect($data['cells'][0]['classes'])->pluck('group')->sort()->values()->all() === ['FSTI', 'TPB'] ? ['TPB', 'FSTI'] : []);
        $this->assertSame(['TPB', 'FSTI', 'FPB', 'FRTI'], array_column($data['legend'], 'label'));
    }

    public function test_room_grid_json_is_null_when_nothing_is_published_and_refuses_other_roles(): void
    {
        SchedulingRun::query()->update(['published_at' => null]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
        $this->getJson('/api/internal/schedules/room-grid')->assertOk()->assertJsonPath('data', null);

        Sanctum::actingAs(User::factory()->lecturer()->create());
        $this->getJson('/api/internal/schedules/room-grid')->assertForbidden();
    }
}
