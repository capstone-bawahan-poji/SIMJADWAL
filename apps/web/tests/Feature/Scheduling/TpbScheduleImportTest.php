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
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\TpbOccupancyImport;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * POS 1.2: a faculty must know which cells TPB holds before it hands out rooms. Besides the TPB
 * GA, the TPB admin can enter the TPB schedule from a file (a TPB run), and a faculty can import
 * the file it got from TPB (its own record).
 */
class TpbScheduleImportTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private const HEADERS = ['Kode MK', 'Nama Mata Kuliah', 'SKS', 'Kelas', 'Hari', 'Sesi', 'Ruang', 'Jumlah Peserta', 'Dosen'];

    private Faculty $faculty;

    private Room $own;

    private Room $shared;

    private Lecturer $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(ConstraintTypeSeeder::class);
        $this->faculty = Faculty::factory()->create();
        foreach ([1, 2, 3, 4] as $day) {
            foreach ([1, 2] as $session) {
                TimeSlot::factory()->create(['day' => $day, 'session' => $session]);
            }
        }
        $this->own = Room::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'E101', 'capacity' => 40]);
        $this->shared = Room::factory()->create(['faculty_id' => null, 'code' => 'G201', 'capacity' => 80]);
        $this->lecturer = Lecturer::factory()->create(['name' => 'Rina Wati', 'title' => 'M.Si.']);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([self::HEADERS, ...$rows]);
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'jadwal-tpb.xlsx', null, null, true);
    }

    private function slot(int $day, int $session): int
    {
        return TimeSlot::query()->where('day', $day)->where('session', $session)->value('id');
    }

    public function test_faculty_imports_the_tpb_file_and_its_cells_become_tpb(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $otherRoom = Room::factory()->create(['faculty_id' => Faculty::factory()->create()->id, 'code' => 'Z900']);
        $file = fn () => $this->xlsx([
            ['KU2512015', 'Matematika 1', 3, 'A', 'Senin', 1, 'E101', 38, 'Rina Wati, M.Si.'],
            ['KU2512016', 'Fisika 1', 4, 'A', 'Selasa', 2, 'g-201', 40, 'Tidak Ada'],
            ['KU2512017', 'Kimia', 3, 'B', 'Rabu', 1, 'Z900', 30, ''],
        ]);

        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => $file(), 'dry_run' => true], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.committed', false)
            ->assertJsonPath('data.rows', 2)
            ->assertJsonPath('data.skipped', 2)
            ->assertJsonCount(2, 'data.warnings');
        $this->assertFalse(TpbOccupancyImport::query()->exists());

        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => $file(), 'dry_run' => false], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.committed', true);

        $this->getJson('/api/v1/tpb-occupancy?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.source.type', 'import')
            ->assertJsonCount(2, 'data.cells')
            ->assertJsonPath('data.import.rows.0.lecturer.id', $this->lecturer->id)
            ->assertJsonPath('data.import.rows.1.lecturer', null);

        $grid = collect($this->getJson('/api/v1/room-slot-allocations?period='.urlencode(self::PERIOD))->assertOk()->json('data.cells'))
            ->keyBy(fn (array $c) => "{$c['time_slot_id']}:{$c['room_id']}");
        $this->assertSame('tpb', $grid["{$this->slot(1, 1)}:{$this->own->id}"]['status']);
        $this->assertSame('tpb', $grid["{$this->slot(2, 2)}:{$this->shared->id}"]['status']);
        $this->assertFalse($grid->has("{$this->slot(3, 1)}:{$otherRoom->id}"));

        // The faculty can now hand out the free cells, never TPB's.
        $program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $this->putJson('/api/v1/room-slot-allocations', ['period' => self::PERIOD, 'cells' => [
            ['time_slot_id' => $this->slot(1, 1), 'room_id' => $this->own->id, 'study_program_id' => $program->id],
        ]])->assertUnprocessable();
        $this->putJson('/api/v1/room-slot-allocations', ['period' => self::PERIOD, 'cells' => [
            ['time_slot_id' => $this->slot(1, 2), 'room_id' => $this->own->id, 'study_program_id' => $program->id],
        ]])->assertOk();

        $this->deleteJson('/api/v1/tpb-occupancy?period='.urlencode(self::PERIOD))->assertNoContent();
        $this->getJson('/api/v1/tpb-occupancy?period='.urlencode(self::PERIOD))->assertJsonPath('data.source.type', null);
    }

    public function test_faculty_import_rejects_unknown_slots_and_needs_its_columns(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());

        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => $this->xlsx([['KU1', 'X', 3, 'A', 'Minggu', 1, 'E101', 10, '']]), 'dry_run' => false], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('data.errors.0.row', 2);

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Kode MK', 'Hari'], ['KU1', 'Senin']]);
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        (new Xlsx($book))->save($path);
        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => new UploadedFile($path, 'x.xlsx', null, null, true), 'dry_run' => true], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        Sanctum::actingAs(User::factory()->studyProgramAdmin(StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]))->create());
        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => $this->xlsx([]), 'dry_run' => true], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_tpb_admin_enters_the_tpb_schedule_without_the_ga(): void
    {
        $math = TpbOffering::factory()->create([
            'course_id' => Course::factory()->tpb()->create(['code' => 'KU2512015', 'sks' => 3])->id,
            'period' => self::PERIOD, 'parallel_class_count' => 2, 'class_capacity' => 40,
        ]);
        $physics = TpbOffering::factory()->create([
            'course_id' => Course::factory()->tpb()->create(['code' => 'KU2512016', 'sks' => 4])->id,
            'period' => self::PERIOD, 'parallel_class_count' => 1, 'class_capacity' => 40,
        ]);
        $program = StudyProgram::factory()->create(['faculty_id' => $this->faculty->id]);
        $mathGroup = TpbGroup::factory()->create(['tpb_offering_id' => $math->id, 'code' => 'G1', 'cohort' => TpbCohort::MABA]);
        $mathGroup->studyPrograms()->attach($program->id, ['target_semester' => 1]);
        $physicsGroup = TpbGroup::factory()->create(['tpb_offering_id' => $physics->id, 'code' => 'G1', 'cohort' => TpbCohort::MABA]);
        $other = Lecturer::factory()->create();
        foreach ([[$math, $mathGroup, 1, $this->lecturer], [$math, $mathGroup, 2, $other], [$physics, $physicsGroup, 1, Lecturer::factory()->create()]] as [$offering, $group, $number, $lecturer]) {
            CourseLecturer::factory()->create([
                'course_id' => $offering->course_id, 'tpb_offering_id' => $offering->id, 'tpb_group_id' => $group->id,
                'class_number' => $number, 'lecturer_id' => $lecturer->id,
            ]);
        }
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());

        // Room twice in one slot, an unknown class, a third meeting of a 4 SKS class.
        $this->post('/api/v1/tpb-schedule/import', ['period' => self::PERIOD, 'dry_run' => true, 'file' => $this->xlsx([
            ['KU2512015', 'Matematika 1', 3, 'A', 'Senin', 1, 'G201', 38, ''],
            ['KU2512015', 'Matematika 1', 3, 'B', 'Senin', 1, 'G201', 38, ''],
            ['KU2512099', 'Lain', 3, 'A', 'Senin', 2, 'G201', 38, ''],
            ['KU2512016', 'Fisika 1', 4, 'A', 'Selasa', 1, 'G201', 40, ''],
            ['KU2512016', 'Fisika 1', 4, 'A', 'Rabu', 1, 'G201', 40, ''],
            ['KU2512016', 'Fisika 1', 4, 'A', 'Kamis', 1, 'G201', 40, ''],
        ])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.committed', false)
            ->assertJsonCount(3, 'data.errors')
            ->assertJsonPath('data.errors.0.row', 3);

        $run = $this->post('/api/v1/tpb-schedule/import', ['period' => self::PERIOD, 'dry_run' => false, 'file' => $this->xlsx([
            ['KU2512015', 'Matematika 1', 3, 'A', 'Senin', 1, 'G201', 38, ''],
            ['KU2512015', 'Matematika 1', 3, 'B', 'Senin', 1, 'E101', 38, ''],
            ['KU2512016', 'Fisika 1', 4, 'A', 'Selasa', 1, 'G201', 40, ''],
            ['KU2512016', 'Fisika 1', 4, 'A', 'Rabu', 2, 'G201', 40, ''],
        ])], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.meetings', 4)
            ->json('data.run_id');

        $this->assertSame('import', SchedulingRun::query()->findOrFail($run)->source->value);
        $this->getJson("/api/v1/scheduling-runs/{$run}")->assertOk()->assertJsonPath('data.source', 'import')->assertJsonPath('data.scope', 'tpb');
        $this->postJson("/api/v1/scheduling-runs/{$run}/activate")->assertOk();
        $this->postJson("/api/v1/scheduling-runs/{$run}/publish")->assertOk();

        // Faculties now see the imported TPB run as TPB data, with the program block of the maba group.
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->getJson('/api/v1/tpb-occupancy?period='.urlencode(self::PERIOD))
            ->assertOk()
            ->assertJsonPath('data.source.type', 'run')
            ->assertJsonPath('data.source.run_id', $run)
            ->assertJsonCount(4, 'data.cells');
    }

    public function test_only_tpb_admin_imports_a_tpb_run(): void
    {
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $this->post('/api/v1/tpb-schedule/import', ['period' => self::PERIOD, 'dry_run' => true, 'file' => $this->xlsx([])], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->get('/api/v1/tpb-schedule/import/template')->assertOk()->assertDownload('template-jadwal-tpb.xlsx');
    }
}
