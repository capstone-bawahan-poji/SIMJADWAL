<?php

namespace Tests\Feature\MasterData;

use App\Enums\Constraint\ConstraintStatus;
use App\Enums\Scheduling\RunStatus;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\MasterData\CourseImport\CourseImportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class CourseImportApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private StudyProgram $program;

    private Lecturer $darmansyah;

    private Lecturer $rizky;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->program = StudyProgram::factory()->create(['code' => 'IF']);
        $this->darmansyah = Lecturer::factory()->create(['study_program_id' => $this->program->id, 'name' => 'Darmansyah', 'title' => 'S.Si., M.T.I']);
        $this->rizky = Lecturer::factory()->create(['study_program_id' => $this->program->id, 'name' => 'Rizky Amelia', 'title' => 'S.Si., M.Han.']);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($this->program)->create());
    }

    public function test_preview_then_commit_creates_courses_classes_and_lecturers(): void
    {
        $file = $this->sheet([
            ['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', 'Darmansyah, S.Si., M.T.I'],
            ['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'b', 'DARMANSYAH'],
            ['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'C', null],
            ['IF2515102', 'Keamanan Siber', 3, 5, 30, 'X', 'Rizky Amelia S.Si., M.Han.'],
        ]);

        $preview = $this->import($file)->assertOk()
            ->assertJsonPath('data.can_commit', true)
            ->assertJsonPath('data.summary.created', 2)
            ->assertJsonPath('data.summary.errors', 0)
            ->assertJsonPath('data.courses.0.fields.parallel_class_count', 3)
            ->assertJsonPath('data.courses.0.classes.1.lecturer.id', $this->darmansyah->id)
            ->assertJsonPath('data.courses.0.classes.2.lecturer', null)
            ->assertJsonPath('data.courses.1.classes.0.label', 'X')
            ->assertJsonPath('data.warnings.0.message', 'Kelas pengayaan X disimpan sebagai kelas A.');

        $this->assertSame(0, Course::query()->count());

        $this->import($file, ['dry_run' => 0, 'plan_hash' => $preview->json('data.plan_hash')])
            ->assertCreated()
            ->assertJsonPath('data', ['created' => 2, 'updated' => 0, 'unchanged' => 0]);

        $diskrit = Course::query()->where('code', 'IF2514101')->firstOrFail();
        $this->assertSame(3, $diskrit->parallel_class_count);
        $this->assertSame([1 => $this->darmansyah->id, 2 => $this->darmansyah->id], $diskrit->courseLecturers()->pluck('lecturer_id', 'class_number')->all());

        $siber = Course::query()->where('code', 'IF2515102')->firstOrFail();
        $this->assertSame(1, $siber->parallel_class_count);
        $this->assertSame([1 => $this->rizky->id], $siber->courseLecturers()->pluck('lecturer_id', 'class_number')->all());
    }

    public function test_upsert_updates_existing_course_and_leaves_courses_outside_the_file(): void
    {
        $course = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF2514301', 'sks' => 2, 'parallel_class_count' => 3]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 1, 'lecturer_id' => $this->darmansyah->id]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 2, 'lecturer_id' => $this->darmansyah->id]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 3, 'lecturer_id' => $this->darmansyah->id]);
        $outside = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF2514999']);

        $file = $this->sheet([
            [$course->code, $course->name, 3, $course->semester, $course->class_capacity, 'A', 'Darmansyah'],
            [$course->code, $course->name, 3, $course->semester, $course->class_capacity, 'B', 'Rizky Amelia'],
        ]);

        $preview = $this->import($file)->assertOk()
            ->assertJsonPath('data.courses.0.status', 'updated')
            ->assertJsonPath('data.courses.0.changes', [
                ['field' => 'sks', 'from' => 2, 'to' => 3],
                ['field' => 'parallel_class_count', 'from' => 3, 'to' => 2],
            ])
            ->assertJsonPath('data.courses.0.classes.0.change', 'same')
            ->assertJsonPath('data.courses.0.classes.1.change', 'lecturer_changed')
            ->assertJsonPath('data.courses.0.classes.1.previous_lecturer.id', $this->darmansyah->id)
            ->assertJsonPath('data.courses.0.classes.2.change', 'removed')
            ->assertJsonPath('data.untouched_courses.0.code', 'IF2514999');

        $this->import($file, ['dry_run' => 0, 'plan_hash' => $preview->json('data.plan_hash')])
            ->assertCreated()
            ->assertJsonPath('data.updated', 1);

        $course->refresh();
        $this->assertSame(3, $course->sks);
        $this->assertSame(2, $course->parallel_class_count);
        $this->assertSame([1 => $this->darmansyah->id, 2 => $this->rizky->id], $course->courseLecturers()->pluck('lecturer_id', 'class_number')->all());
        $this->assertNotSoftDeleted($outside);

        // Same file again: nothing left to change.
        $this->import($file)->assertOk()->assertJsonPath('data.courses.0.status', 'unchanged');
    }

    public function test_any_error_blocks_the_whole_import(): void
    {
        Course::factory()->create(['code' => 'SI2514101']);

        $file = $this->sheet([
            ['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', null],
            ['IF2514101', 'Matematika Diskrit', 2, 1, 40, 'C', null],
            ['IF2514102', 'Pengantar Informatika', 3, 1, 40, 'A', null],
            ['IF2514102', 'Pengantar Informatika', 3, 1, 40, 'X', null],
            ['SI2514101', 'Milik Prodi Lain', 3, 1, 40, 'A', null],
            ['IF2514103', 'Sistem Digital', 5, 9, 40, 'A', 'Dosen Tidak Ada'],
            ['IF2514104', 'Valid', 3, 1, 40, 'A', null],
        ]);

        $preview = $this->import($file)->assertOk()
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.summary.invalid', 4)
            ->assertJsonPath('data.summary.created', 1);

        $messages = collect($preview->json('data.errors'))->map(fn (array $error) => "{$error['row']}|{$error['column']}|{$error['message']}")->all();
        $this->assertContains('3|SKS|Berbeda dengan baris 2 untuk Kode MK yang sama.', $messages);
        $this->assertContains('3|Kelas|Kelas harus berurutan dari A tanpa lompat (A, B, C, ...).', $messages);
        $this->assertContains('5|Kelas|Kelas X dihitung sebagai kelas A, jadi mata kuliah dengan kelas X hanya boleh punya satu kelas.', $messages);
        $this->assertContains('6|Kode MK|Kode MK SI2514101 sudah dipakai oleh '.Course::query()->where('code', 'SI2514101')->firstOrFail()->studyProgram->code.'.', $messages);
        $this->assertContains('7|SKS|SKS harus 2, 3, atau 4.', $messages);
        $this->assertContains('7|Dosen Pengampu|Dosen "Dosen Tidak Ada" belum terdaftar. Pilih dosen di daftar pencocokan dosen, atau tambahkan dosen tersebut terlebih dahulu.', $messages);
        $this->assertNotEmpty($preview->json('data.lecturer_options'));

        $this->import($file, ['dry_run' => 0, 'plan_hash' => $preview->json('data.plan_hash')])
            ->assertUnprocessable()
            ->assertJsonPath('data.can_commit', false);

        $this->assertSame(1, Course::query()->count());
    }

    public function test_ambiguous_lecturer_is_resolved_by_choice(): void
    {
        $other = Lecturer::factory()->create(['study_program_id' => $this->program->id, 'name' => 'Darmansyah', 'title' => 'S.T.']);
        $file = $this->sheet([
            ['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', 'Darmansyah, S.Si., M.T.I'],
        ]);

        $this->import($file)->assertOk()
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.lecturer_matches.0.status', 'ambiguous')
            ->assertJsonPath('data.lecturer_matches.0.key', 'darmansyah')
            ->assertJsonCount(2, 'data.lecturer_matches.0.candidates');

        $resolutions = json_encode(['darmansyah' => $other->id]);
        $preview = $this->import($file, ['resolutions' => $resolutions])->assertOk()
            ->assertJsonPath('data.can_commit', true)
            ->assertJsonPath('data.lecturer_matches.0.status', 'resolved')
            ->assertJsonPath('data.courses.0.classes.0.lecturer.id', $other->id);

        $this->import($file, ['dry_run' => 0, 'resolutions' => $resolutions, 'plan_hash' => $preview->json('data.plan_hash')])->assertCreated();
        $this->assertSame($other->id, CourseLecturer::query()->value('lecturer_id'));
    }

    public function test_lecturer_of_own_program_wins_over_same_name_elsewhere_and_suggestions_are_given(): void
    {
        Lecturer::factory()->create(['name' => 'Rizky Amelia']);

        $this->import($this->sheet([
            ['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', 'Rizky Amelia, S.Si., M.Han.'],
            ['IF2514102', 'Pengantar Informatika', 3, 1, 40, 'A', 'Rizky Amelya'],
        ]))->assertOk()
            ->assertJsonPath('data.lecturer_matches.0.status', 'matched')
            ->assertJsonPath('data.lecturer_matches.0.lecturer.id', $this->rizky->id)
            ->assertJsonPath('data.lecturer_matches.1.status', 'not_found')
            ->assertJsonPath('data.lecturer_matches.1.candidates.0.name', 'Rizky Amelia');
    }

    public function test_commit_refuses_when_data_changed_after_preview(): void
    {
        $file = $this->sheet([['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', null]]);
        $hash = $this->import($file)->json('data.plan_hash');

        Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF2514101', 'sks' => 2]);

        $this->import($file, ['dry_run' => 0, 'plan_hash' => $hash])
            ->assertStatus(409)
            ->assertJsonPath('code', 'IMPORT_PLAN_CHANGED');
    }

    public function test_dropping_a_class_of_an_active_schedule_is_refused(): void
    {
        $course = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF2514101', 'parallel_class_count' => 2]);
        $run = SchedulingRun::query()->create([
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => $this->program->faculty_id,
            'period' => '2026/2027-GANJIL',
            'parameter_snapshot' => [],
            'status' => RunStatus::DONE,
            'is_active' => true,
            'triggered_by' => User::factory()->create()->id,
        ]);
        ScheduleDetail::query()->create([
            'scheduling_run_id' => $run->id,
            'course_id' => $course->id,
            'class_number' => 2,
            'meeting_number' => 1,
            'lecturer_id' => $this->darmansyah->id,
            'time_slot_id' => TimeSlot::factory()->create()->id,
            'room_id' => Room::factory()->create()->id,
        ]);

        $this->import($this->sheet([[$course->code, $course->name, $course->sks, $course->semester, $course->class_capacity, 'A', null]]))
            ->assertOk()
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.errors.0.message', 'Kelas B masih dipakai jadwal aktif.');
    }

    public function test_locked_program_blocks_program_admin_but_not_faculty_admin(): void
    {
        $this->program->update(['constraint_status' => ConstraintStatus::SUBMITTED, 'constraint_submitted_at' => now()]);
        $file = $this->sheet([['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', null]]);

        $this->import($file)->assertStatus(409)->assertJsonPath('code', 'CONSTRAINTS_LOCKED');

        Sanctum::actingAs(User::factory()->facultyAdmin($this->program->faculty)->create());
        $this->import($file)->assertOk();

        Sanctum::actingAs(User::factory()->studyProgramAdmin(StudyProgram::factory()->create())->create());
        $this->import($file)->assertForbidden();
    }

    public function test_file_level_problems_are_validation_errors(): void
    {
        $this->import($this->sheet([['IF2514101', 'Matematika Diskrit']], ['Kode MK', 'Mata Kuliah']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Kolom wajib tidak ditemukan: SKS, Semester, Kapasitas, Kelas.');

        $this->import($this->sheet([]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'File tidak berisi baris mata kuliah.');

        $this->import(UploadedFile::fake()->createWithContent('mk.xlsx', 'not a spreadsheet'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'File tidak dapat dibaca. Unggah template .xlsx dari sistem.');

        $this->import($this->sheet([['IF2514101', 'Matematika Diskrit', 3, 1, 40, 'A', null]]), ['dry_run' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_hash');
    }

    public function test_template_is_prefilled_with_current_courses(): void
    {
        $course = Course::factory()->create(['study_program_id' => $this->program->id, 'code' => 'IF2514101', 'name' => 'Matematika Diskrit', 'parallel_class_count' => 2]);
        CourseLecturer::factory()->create(['course_id' => $course->id, 'class_number' => 1, 'lecturer_id' => $this->darmansyah->id]);

        $response = $this->get("/api/v1/courses/import/template?study_program_id={$this->program->id}&prefill=1")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Mata Kuliah');

        $this->assertSame(CourseImportTemplate::HEADERS, $sheet->rangeToArray('A1:H1')[0]);
        $this->assertSame(['IF2514101', 'Matematika Diskrit', 'A', 'Darmansyah, S.Si., M.T.I'], array_values(array_intersect_key($sheet->rangeToArray('A2:H2')[0], array_flip([1, 2, 6, 7]))));
        $this->assertSame('B', $sheet->getCell('G3')->getValue());
        $this->assertNull($sheet->getCell('H3')->getValue());
        $this->assertSame('"2,3,4"', $sheet->getCell('D2')->getDataValidation()->getFormula1());
        $this->assertSame(['Mata Kuliah'], $spreadsheet->getSheetNames());
        $this->assertFalse($sheet->getCell('H2')->hasDataValidation());

        // The prefilled file imports back with nothing to change.
        $this->import(new UploadedFile($path, 'template.xlsx', null, null, true))
            ->assertOk()
            ->assertJsonPath('data.can_commit', true)
            ->assertJsonPath('data.courses.0.status', 'unchanged');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function import(UploadedFile $file, array $overrides = []): TestResponse
    {
        return $this->post('/api/v1/courses/import', [
            'study_program_id' => $this->program->id,
            'file' => $file,
            'dry_run' => 1,
            ...$overrides,
        ], ['Accept' => 'application/json']);
    }

    /**
     * @param  list<list<int|string|null>>  $rows  Kode MK .. Dosen Pengampu
     * @param  list<string>|null  $header
     */
    private function sheet(array $rows, ?array $header = null): UploadedFile
    {
        $header ??= array_slice(CourseImportTemplate::HEADERS, 1);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$header, ...$rows], null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'mk').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'mata-kuliah.xlsx', null, null, true);
    }
}
