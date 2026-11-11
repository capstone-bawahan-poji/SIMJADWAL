<?php

namespace Tests\Feature\Scheduling;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbOffering;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\TpbOccupancyImport;
use App\Models\User;
use Database\Seeders\Reference\ConstraintTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * One TPB file format for the data import, the schedule import (TPB admin), the faculty import and
 * the export: No | Kode MK | Mata Kuliah | SKS | Grup | Peserta | Prodi | Semester | Kelas | Kapasitas
 * | Dosen Pengampu | Ruangan | Hari | Sesi | Pertemuan (docs/plans/konvensi-file-tpb.md).
 */
class TpbFileConventionTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private const HEADERS = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Grup', 'Peserta', 'Prodi', 'Semester', 'Kelas', 'Kapasitas', 'Dosen Pengampu', 'Ruangan', 'Hari', 'Sesi', 'Pertemuan'];

    private Faculty $faculty;

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
        StudyProgram::factory()->create(['code' => 'IF', 'faculty_id' => $this->faculty->id]);
        StudyProgram::factory()->create(['code' => 'SI', 'faculty_id' => $this->faculty->id]);
        Room::factory()->shared()->create(['code' => 'G201', 'capacity' => 80]);
        Room::factory()->shared()->create(['code' => 'G202', 'capacity' => 80]);
        Lecturer::factory()->create(['name' => 'Rina Wati', 'title' => 'M.Si.']);
        Lecturer::factory()->create(['name' => 'Budi Santoso', 'title' => 'M.T.']);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
    }

    /**
     * Matematika 1 (3 SKS, group G1 of IF and SI, classes A and B) and Fisika 1 (4 SKS, one class, two meetings).
     *
     * @return list<list<int|string|null>>
     */
    private function rows(): array
    {
        return [
            [1, 'KU9001', 'Matematika 1', 3, 'G1', 'Maba', 'IF,SI', null, 'A', 40, 'Rina Wati, M.Si.', 'G201', 'Senin', 'Sesi 1', 1],
            [2, 'KU9001', 'Matematika 1', 3, 'G1', null, null, null, 'B', 40, 'Budi Santoso, M.T.', 'G202', 'Senin', 1, 1],
            [3, 'KU9002', 'Fisika 1', 4, 'G1', 'Non Maba', 'IF', 3, 'A', 40, 'Rina Wati, M.Si.', 'G201', 'Selasa', 1, 1],
            [4, 'KU9002', 'Fisika 1', 4, 'G1', null, null, null, 'A', 40, 'Rina Wati, M.Si.', 'G201', 'Rabu', 'Sesi 2', 2],
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<int|string|null>>  $rows
     */
    private function xlsx(array $rows, array $headers = self::HEADERS): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([$headers, ...$rows]);
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'tpb.xlsx', null, null, true);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function importSchedule(UploadedFile $file, bool $dryRun)
    {
        return $this->post('/api/v1/tpb-schedule/import', ['period' => self::PERIOD, 'dry_run' => $dryRun, 'file' => $file], ['Accept' => 'application/json']);
    }

    public function test_one_file_creates_the_groups_and_the_tpb_run(): void
    {
        $this->importSchedule($this->xlsx($this->rows()), true)
            ->assertOk()
            ->assertJsonPath('data.committed', false)
            ->assertJsonPath('data.meetings', 4)
            ->assertJsonPath('data.data.offerings', 2)
            ->assertJsonPath('data.data.groups', 2)
            ->assertJsonPath('data.data.classes', 3)
            ->assertJsonCount(0, 'data.errors');
        $this->assertSame(0, Course::query()->where('code', 'KU9001')->count());
        $this->assertSame(0, SchedulingRun::query()->count());

        $runId = $this->importSchedule($this->xlsx($this->rows()), false)
            ->assertCreated()
            ->assertJsonPath('data.committed', true)
            ->json('data.run_id');

        $physics = TpbOffering::query()->whereRelation('course', 'code', 'KU9002')->sole();
        $group = $physics->groups()->sole();
        $this->assertSame('non_maba', $group->cohort->value);
        $this->assertSame(3, (int) $group->studyPrograms()->sole()->pivot->target_semester);
        $this->assertSame(['source' => 'import', 'details' => 4], [
            'source' => SchedulingRun::query()->findOrFail($runId)->source->value,
            'details' => ScheduleDetail::query()->where('scheduling_run_id', $runId)->count(),
        ]);
        $meetings = ScheduleDetail::query()->where('scheduling_run_id', $runId)->where('course_id', $physics->course_id)->orderBy('meeting_number')->get();
        $this->assertSame([1, 2], $meetings->pluck('meeting_number')->all());
        $this->assertSame(
            [TimeSlot::query()->where('day', 2)->where('session', 1)->value('id'), TimeSlot::query()->where('day', 3)->where('session', 2)->value('id')],
            $meetings->pluck('time_slot_id')->all(),
        );
    }

    public function test_group_data_without_schedule_cells_applies_the_data_only(): void
    {
        $rows = array_map(fn (array $row) => [...array_slice($row, 0, 11), null, null, null, null], $this->rows());
        $rows = array_values(array_filter($rows, fn (array $row) => $row[0] !== 4));

        $this->importSchedule($this->xlsx($rows), false)
            ->assertCreated()
            ->assertJsonPath('data.committed', true)
            ->assertJsonPath('data.run_id', null)
            ->assertJsonPath('data.meetings', 0)
            ->assertJsonPath('data.data.classes', 3);
        $this->assertSame(2, TpbOffering::query()->count());
        $this->assertSame(0, SchedulingRun::query()->count());
    }

    public function test_data_import_ignores_schedule_columns_and_meeting_two_rows(): void
    {
        $this->postJson('/api/v1/tpb-offerings/import', ['period' => self::PERIOD, 'dry_run' => false, 'file' => $this->xlsx($this->rows())])
            ->assertCreated()
            ->assertJsonPath('data.classes', 3)
            ->assertJsonCount(0, 'data.errors');

        $this->assertSame(3, CourseLecturer::query()->count());
        $this->assertSame(0, ScheduleDetail::query()->count());
    }

    public function test_rows_of_one_group_must_agree_on_peserta_prodi_and_semester(): void
    {
        $rows = $this->rows();
        $rows[1][5] = 'Mengulang';

        $this->importSchedule($this->xlsx($rows), true)
            ->assertOk()
            ->assertJsonPath('data.committed', false)
            ->assertJsonPath('data.errors.0.row', 2);
        $this->assertStringContainsString('grup G1', $this->importSchedule($this->xlsx($rows), true)->json('data.errors.0.message'));
    }

    public function test_session_accepts_a_number_or_the_word_sesi(): void
    {
        $rows = $this->rows();
        $rows[0][13] = 'sesi 1';
        $rows[1][13] = '1';

        $this->importSchedule($this->xlsx($rows), true)->assertOk()->assertJsonCount(0, 'data.errors');

        $rows[1][13] = 'Sesi satu';
        $this->importSchedule($this->xlsx($rows), true)->assertOk()->assertJsonPath('data.errors.0.row', 3);
    }

    public function test_meeting_column_is_checked_against_the_sks(): void
    {
        $rows = $this->rows();
        $rows[3][14] = 3;
        $this->importSchedule($this->xlsx($rows), true)->assertOk()->assertJsonPath('data.errors.0.row', 5);

        $rows[3][14] = 1;
        $this->importSchedule($this->xlsx($rows), true)->assertOk()->assertJsonPath('data.errors.0.row', 5);

        // The same class twice with nothing that tells them apart is a duplicate, not a second meeting.
        $duplicate = $this->rows();
        $duplicate[3] = [4, 'KU9002', 'Fisika 1', 4, 'G1', null, null, null, 'A', 40, 'Rina Wati, M.Si.', null, null, null, null];
        $this->importSchedule($this->xlsx($duplicate), true)->assertOk()->assertJsonPath('data.errors.0.row', 4);

        // Without the column the rows of a class count 1, 2, ...
        $rows[3][14] = null;
        $this->importSchedule($this->xlsx($rows), true)->assertOk()->assertJsonCount(0, 'data.errors');
    }

    public function test_a_peserta_column_of_numbers_is_read_as_the_head_count(): void
    {
        $faculty = $this->faculty;
        Room::factory()->create(['faculty_id' => $faculty->id, 'code' => 'E101', 'capacity' => 40]);
        Sanctum::actingAs(User::factory()->facultyAdmin($faculty)->create());

        $file = $this->xlsx([['KU2512015', 'A', 'Senin', 1, 'E101', 38]], ['Kode MK', 'Kelas', 'Hari', 'Sesi', 'Ruang', 'Peserta']);
        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => $file, 'dry_run' => false], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(0, 'data.errors');

        $this->getJson('/api/v1/tpb-occupancy?period='.urlencode(self::PERIOD))->assertOk()->assertJsonPath('data.import.rows.0.participants', 38);
    }

    public function test_faculty_import_reads_the_shared_file_and_ignores_the_group_columns(): void
    {
        Room::factory()->create(['faculty_id' => $this->faculty->id, 'code' => 'E101', 'capacity' => 40]);
        Sanctum::actingAs(User::factory()->facultyAdmin($this->faculty)->create());
        $rows = [
            [1, 'KU9001', 'Matematika 1', 3, 'G1', 'Maba', 'IF,SI', 1, 'A', 40, 'Rina Wati, M.Si.', 'E101', 'Senin', 'Sesi 1', 1],
            [2, 'KU9001', 'Matematika 1', 3, 'G1', null, null, null, 'B', 40, null, null, null, null, null],
            [3, 'KU9002', 'Fisika 1', 4, 'G2', 'Kelompok Aneh', 'XX', 99, 'A', 40, null, 'E101', 'Selasa', 2, 1],
        ];

        $this->post('/api/v1/tpb-occupancy/import', ['period' => self::PERIOD, 'file' => $this->xlsx($rows), 'dry_run' => false], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.rows', 2)
            ->assertJsonCount(0, 'data.errors')
            ->assertJsonCount(1, 'data.warnings');

        $this->assertSame(1, TpbOccupancyImport::query()->count());
        $this->assertSame(0, TpbOffering::query()->count());
    }

    public function test_template_and_export_use_the_same_fifteen_columns(): void
    {
        $blank = $this->get('/api/v1/tpb-schedule/import/template')->assertOk()->assertDownload('template-jadwal-tpb.xlsx');
        $book = $this->loadDownload($blank->streamedContent());
        $this->assertSame(self::HEADERS, array_slice($book->getSheetByName('TPB')->toArray(null, true, false, false)[0], 0, 15));
        $this->assertNotNull($book->getSheetByName('Petunjuk'));

        $dataTemplate = $this->get('/api/v1/tpb-offerings/import/template?period='.self::PERIOD)->assertOk();
        $this->assertSame(self::HEADERS, array_slice($this->loadDownload($dataTemplate->streamedContent())->getSheetByName('TPB')->toArray(null, true, false, false)[0], 0, 15));
    }

    public function test_prefilled_export_carries_the_active_run_and_imports_back(): void
    {
        $runId = $this->importSchedule($this->xlsx($this->rows()), false)->assertCreated()->json('data.run_id');
        $this->postJson("/api/v1/scheduling-runs/{$runId}/activate")->assertOk();

        $response = $this->get('/api/v1/tpb-offerings/import/template?period='.self::PERIOD.'&prefill=1')->assertOk();
        $content = $response->streamedContent();
        $sheet = $this->loadDownload($content)->getSheetByName('TPB');
        $table = $sheet->toArray(null, true, false, false);

        $this->assertSame(self::HEADERS, array_slice($table[0], 0, 15));
        $this->assertCount(5, $table);
        // Matematika: group data on the first row only, then the place of each class.
        $this->assertSame(['KU9001', 'G1', 'Maba', 'IF,SI', 'A', 'G201', 'Senin', 1, 1], [$table[1][1], $table[1][4], $table[1][5], $table[1][6], $table[1][8], $table[1][11], $table[1][12], $table[1][13], $table[1][14]]);
        $this->assertSame(['B', 'G202', 1], [$table[2][8], $table[2][11], $table[2][14]]);
        // Fisika 4 SKS: two rows for the same class, meeting 1 and 2.
        $this->assertSame(['A', 'Selasa', 1, 1], [$table[3][8], $table[3][12], $table[3][13], $table[3][14]]);
        $this->assertSame(['A', 'Rabu', 2, 2], [$table[4][8], $table[4][12], $table[4][13], $table[4][14]]);

        // The export can be imported again as it is.
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        file_put_contents($path, $content);
        $this->importSchedule(new UploadedFile($path, 'export.xlsx', null, null, true), true)
            ->assertOk()
            ->assertJsonPath('data.meetings', 4)
            ->assertJsonCount(0, 'data.errors');
    }

    private function loadDownload(string $content): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        file_put_contents($path, $content);

        return IOFactory::load($path);
    }
}
