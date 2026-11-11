<?php

namespace Tests\Feature\MasterData;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

/**
 * TPB data: a course is offered per period, its classes are split into groups of programs,
 * and each class then gets a lecturer. Slots come from the GA, never from these endpoints.
 */
class TpbApiTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PERIOD = '2026/2027-GANJIL';

    private Course $calculus;

    private TpbOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->calculus = Course::factory()->tpb()->create(['name' => 'Matematika 1', 'sks' => 3]);
        $this->offering = TpbOffering::factory()->create(['course_id' => $this->calculus->id, 'period' => self::PERIOD, 'parallel_class_count' => 5]);
        Sanctum::actingAs(User::factory()->tpbAdmin()->create());
    }

    public function test_tpb_course_has_no_semester_class_count_or_capacity(): void
    {
        $this->postJson('/api/v1/courses', ['is_tpb' => true, 'code' => 'KU2512025', 'name' => 'Matematika 1', 'sks' => 3])
            ->assertCreated()
            ->assertJsonPath('data.semester', null)
            ->assertJsonPath('data.parallel_class_count', null);

        $this->postJson('/api/v1/courses', ['is_tpb' => true, 'code' => 'KU2512035', 'name' => 'Matematika 1', 'sks' => 2, 'semester' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('semester');
    }

    public function test_one_course_is_offered_once_per_period(): void
    {
        $this->postJson('/api/v1/tpb-offerings', [
            'course_id' => $this->calculus->id, 'period' => '2026/2027-GENAP', 'parallel_class_count' => 3, 'class_capacity' => 40,
        ])->assertCreated()->assertJsonPath('data.course.name', 'Matematika 1');

        $this->postJson('/api/v1/tpb-offerings', [
            'course_id' => $this->calculus->id, 'period' => self::PERIOD, 'parallel_class_count' => 3, 'class_capacity' => 40,
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');
    }

    public function test_group_creates_its_classes_without_lecturer(): void
    {
        $programs = StudyProgram::factory()->count(2)->create();

        $this->postJson('/api/v1/tpb-groups', [
            'tpb_offering_id' => $this->offering->id,
            'code' => 'G1',
            'cohort' => 'maba',
            'study_programs' => $programs->map(fn ($p) => ['id' => $p->id, 'target_semester' => 1])->all(),
            'class_numbers' => [1, 2, 3],
        ])
            ->assertCreated()
            ->assertJsonPath('data.cohort', 'maba')
            ->assertJsonPath('data.class_numbers', [1, 2, 3])
            ->assertJsonPath('data.participants.0.target_semester', 1);

        $this->assertSame(3, CourseLecturer::query()->where('tpb_offering_id', $this->offering->id)->whereNull('lecturer_id')->count());
        $this->getJson("/api/v1/tpb-offerings/{$this->offering->id}")
            ->assertJsonPath('data.course_lecturers_count', 3)
            ->assertJsonPath('data.assigned_classes_count', 0);
    }

    public function test_group_rules_follow_the_offering(): void
    {
        $program = StudyProgram::factory()->create();
        $base = ['tpb_offering_id' => $this->offering->id, 'code' => 'G1', 'cohort' => 'maba', 'class_numbers' => [1]];

        // Class beyond the offering, even semester in a GANJIL period, retake with a semester.
        $this->postJson('/api/v1/tpb-groups', [...$base, 'class_numbers' => [6], 'study_programs' => [['id' => $program->id, 'target_semester' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('class_numbers');
        $this->postJson('/api/v1/tpb-groups', [...$base, 'study_programs' => [['id' => $program->id, 'target_semester' => 2]]])
            ->assertUnprocessable()->assertJsonValidationErrors('study_programs');
        $this->postJson('/api/v1/tpb-groups', [...$base, 'cohort' => 'mengulang', 'study_programs' => [['id' => $program->id, 'target_semester' => 3]]])
            ->assertUnprocessable()->assertJsonValidationErrors('study_programs.0.target_semester');
        $this->postJson('/api/v1/tpb-groups', [...$base, 'cohort' => 'pilihan', 'study_programs' => [['id' => $program->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('study_programs');

        $this->postJson('/api/v1/tpb-groups', [...$base, 'cohort' => 'pilihan'])->assertCreated();
    }

    public function test_class_belongs_to_one_group_and_program_to_one_group_per_semester(): void
    {
        $program = StudyProgram::factory()->create(['code' => 'IF']);
        $this->group('G1', TpbCohort::MABA, [$program->id => 1], [1, 2]);

        $this->postJson('/api/v1/tpb-groups', [
            'tpb_offering_id' => $this->offering->id, 'code' => 'G2', 'cohort' => 'maba',
            'study_programs' => [['id' => StudyProgram::factory()->create()->id, 'target_semester' => 1]], 'class_numbers' => [2, 3],
        ])->assertUnprocessable()->assertJsonValidationErrors('class_numbers');

        $this->postJson('/api/v1/tpb-groups', [
            'tpb_offering_id' => $this->offering->id, 'code' => 'G2', 'cohort' => 'campuran',
            'study_programs' => [['id' => $program->id, 'target_semester' => 1]], 'class_numbers' => [3],
        ])->assertUnprocessable()->assertJsonValidationErrors('study_programs');

        // The same program in a non_maba group of semester 3 and in a retake group is fine.
        $this->postJson('/api/v1/tpb-groups', [
            'tpb_offering_id' => $this->offering->id, 'code' => 'G2', 'cohort' => 'non_maba',
            'study_programs' => [['id' => $program->id, 'target_semester' => 3]], 'class_numbers' => [3],
        ])->assertCreated();
        $this->postJson('/api/v1/tpb-groups', [
            'tpb_offering_id' => $this->offering->id, 'code' => 'G3', 'cohort' => 'mengulang',
            'study_programs' => [['id' => $program->id]], 'class_numbers' => [4],
        ])->assertCreated();
    }

    public function test_removing_a_class_from_its_group_deletes_the_class(): void
    {
        $group = $this->group('G1', TpbCohort::MABA, [StudyProgram::factory()->create()->id => 1], [1, 2, 3]);
        CourseLecturer::query()->where('tpb_group_id', $group->id)->update(['lecturer_id' => Lecturer::factory()->create()->id]);

        $this->patchJson("/api/v1/tpb-groups/{$group->id}", ['class_numbers' => [1, 4]])
            ->assertOk()
            ->assertJsonPath('data.class_numbers', [1, 4]);

        $classes = CourseLecturer::query()->where('tpb_group_id', $group->id)->orderBy('class_number')->get();
        $this->assertSame([1, 4], $classes->pluck('class_number')->all());
        $this->assertNotNull($classes[0]->lecturer_id);
        $this->assertNull($classes[1]->lecturer_id);
    }

    public function test_lecturer_is_set_and_cleared_on_the_class_only(): void
    {
        $group = $this->group('G1', TpbCohort::MABA, [StudyProgram::factory()->create()->id => 1], [1]);
        $class = $group->courseLecturers()->first();
        $lecturer = Lecturer::factory()->create();

        $this->patchJson("/api/v1/teaching-assignments/{$class->id}", ['lecturer_id' => $lecturer->id])
            ->assertOk()->assertJsonPath('data.lecturer.id', $lecturer->id);
        $this->patchJson("/api/v1/teaching-assignments/{$class->id}", ['class_number' => 2])
            ->assertUnprocessable();
        $this->patchJson("/api/v1/teaching-assignments/{$class->id}", ['lecturer_id' => null])
            ->assertOk()->assertJsonPath('data.lecturer', null);
        $this->deleteJson("/api/v1/teaching-assignments/{$class->id}")->assertUnprocessable();
        $this->postJson('/api/v1/teaching-assignments', [
            'course_id' => $this->calculus->id, 'class_number' => 5, 'lecturer_id' => $lecturer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('course_id');
    }

    public function test_offering_keeps_grouped_classes_and_groups(): void
    {
        $this->group('G1', TpbCohort::MABA, [StudyProgram::factory()->create()->id => 1], [1, 4]);

        $this->patchJson("/api/v1/tpb-offerings/{$this->offering->id}", ['parallel_class_count' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('parallel_class_count');
        $this->deleteJson("/api/v1/tpb-offerings/{$this->offering->id}")
            ->assertStatus(409)->assertJsonPath('errors.references.tpb_groups', 1);
    }

    public function test_program_admin_reads_tpb_data_but_cannot_write(): void
    {
        $informatics = StudyProgram::factory()->create();
        $this->group('G1', TpbCohort::MABA, [$informatics->id => 1], [1]);
        Sanctum::actingAs(User::factory()->studyProgramAdmin($informatics)->create());

        $this->getJson('/api/v1/tpb-groups')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tpb-offerings')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/tpb-groups', [])->assertForbidden();
        $this->postJson('/api/v1/tpb-offerings', [])->assertForbidden();
    }

    public function test_excel_import_previews_then_creates_offering_groups_and_classes(): void
    {
        StudyProgram::factory()->create(['code' => 'IF']);
        StudyProgram::factory()->create(['code' => 'SI']);
        $lecturer = Lecturer::factory()->create(['nip' => '1990', 'name' => 'Budi Santoso', 'title' => 'M.T.']);
        $file = $this->xlsx([
            ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Grup', 'Peserta', 'Prodi', 'Semester', 'Kelas', 'Kapasitas', 'Dosen Pengampu'],
            [1, 'KU9001', 'Fisika 1', 3, 'G1', 'Maba', 'IF,SI', null, 'A', 40, 'Budi Santoso, M.T.'],
            [2, 'KU9001', 'Fisika 1', 3, 'G1', null, null, null, 'B', 45, null],
            [3, 'KU9001', 'Fisika 1', 3, 'G2', 'Mengulang', 'IF', null, 'C', 40, null],
        ]);

        $this->postJson('/api/v1/tpb-offerings/import', ['period' => self::PERIOD, 'dry_run' => true, 'file' => $file])
            ->assertOk()->assertJsonPath('data.committed', false)->assertJsonPath('data.groups', 2)->assertJsonPath('data.classes', 3)->assertJsonCount(0, 'data.errors');
        $this->assertSame(0, Course::query()->where('code', 'KU9001')->count());

        $this->postJson('/api/v1/tpb-offerings/import', ['period' => self::PERIOD, 'dry_run' => false, 'file' => $file])
            ->assertCreated()->assertJsonPath('data.committed', true);

        $offering = TpbOffering::query()->whereRelation('course', 'code', 'KU9001')->sole();
        $this->assertSame([3, 45], [$offering->parallel_class_count, $offering->class_capacity]);
        $this->assertSame($lecturer->id, CourseLecturer::query()->where('tpb_offering_id', $offering->id)->where('class_number', 1)->value('lecturer_id'));
        $this->assertSame(1, (int) $offering->groups()->where('code', 'G1')->sole()->studyPrograms()->first()->pivot->target_semester);

        // Importing again replaces the groups of the course instead of failing or duplicating them.
        $this->postJson('/api/v1/tpb-offerings/import', ['period' => self::PERIOD, 'dry_run' => false, 'file' => $file])
            ->assertCreated()->assertJsonPath('data.replaced', 1)->assertJsonPath('data.groups', 2);
        $this->assertSame(2, $offering->groups()->count());
        $this->assertSame(3, CourseLecturer::query()->where('tpb_offering_id', $offering->id)->count());
    }

    public function test_excel_import_matches_lecturer_by_name_and_reports_unknown_names(): void
    {
        StudyProgram::factory()->create(['code' => 'IF']);
        Lecturer::factory()->create(['name' => 'Budi Santoso', 'title' => null]);
        $headers = ['No', 'Kode MK', 'Mata Kuliah', 'SKS', 'Grup', 'Peserta', 'Prodi', 'Semester', 'Kelas', 'Kapasitas', 'Dosen Pengampu'];

        $this->postJson('/api/v1/tpb-offerings/import', ['period' => self::PERIOD, 'dry_run' => true, 'file' => $this->xlsx([
            $headers, [1, 'KU9002', 'Kimia', 3, 'G1', 'Maba', 'IF', null, 'A', 40, 'budi santoso'],
        ])])->assertOk()->assertJsonCount(0, 'data.errors');

        $this->postJson('/api/v1/tpb-offerings/import', ['period' => self::PERIOD, 'dry_run' => true, 'file' => $this->xlsx([
            $headers, [1, 'KU9002', 'Kimia', 3, 'G1', 'Maba', 'IF', null, 'A', 40, 'Nama Tidak Ada'],
        ])])->assertOk()->assertJsonPath('data.errors.0.row', 2);
    }

    public function test_prefilled_template_lists_current_classes_with_lecturer_names(): void
    {
        $lecturer = Lecturer::factory()->create(['name' => 'Siti Aminah', 'title' => 'M.Sc.']);
        $program = StudyProgram::factory()->create(['code' => 'IF']);
        $group = $this->group('G1', TpbCohort::MABA, [$program->id => 1], [1, 2]);
        CourseLecturer::query()->where('tpb_group_id', $group->id)->where('class_number', 1)->update(['lecturer_id' => $lecturer->id]);

        $response = $this->get('/api/v1/tpb-offerings/import/template?period='.self::PERIOD.'&prefill=1')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getSheetByName('TPB');

        $this->assertSame('Dosen Pengampu', $sheet->getCell('K1')->getValue());
        $this->assertSame('Siti Aminah, M.Sc.', $sheet->getCell('K2')->getValue());
        $this->assertSame('Maba', $sheet->getCell('F2')->getValue());
        $this->assertSame('IF', $sheet->getCell('G2')->getValue());
        $this->assertSame('B', $sheet->getCell('I3')->getValue());
    }

    private function xlsx(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'tpb').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'tpb.xlsx', null, null, true);
    }

    /**
     * @param  array<int, int|null>  $programs  study_program_id => target semester
     * @param  list<int>  $classes
     */
    private function group(string $code, TpbCohort $cohort, array $programs, array $classes): TpbGroup
    {
        $group = TpbGroup::factory()->create(['tpb_offering_id' => $this->offering->id, 'code' => $code, 'cohort' => $cohort]);
        $group->studyPrograms()->sync(collect($programs)->map(fn (?int $semester) => ['target_semester' => $semester])->all());
        foreach ($classes as $number) {
            CourseLecturer::factory()->forTpbGroup($group)->create(['class_number' => $number, 'lecturer_id' => null]);
        }

        return $group;
    }
}
