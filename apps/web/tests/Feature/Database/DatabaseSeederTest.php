<?php

namespace Tests\Feature\Database;

use App\Enums\Account\Role;
use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbOffering;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\RoomSlotAllocation;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulePublication;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Services\Scheduling\TpbBlockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\SchedulingRunSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_matches_database_md(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Faculty::count());
        $this->assertSame(25, StudyProgram::count());
        $this->assertSame(20, TimeSlot::count());
        $this->assertSame(54, Room::count());
        $this->assertSame(8, ConstraintType::count());
        $this->assertTrue(User::where('email', 'superadmin@example.test')->first()->hasRole('superadmin'));
    }

    /**
     * Re-seeding must not overwrite constraint weights a faculty admin already changed.
     */
    public function test_reference_data_carries_codes_and_campus_slot_hours(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['FPB', 'FRTI', 'FSTI'], Faculty::orderBy('code')->pluck('code')->all());
        $this->assertSame('Informatika', StudyProgram::where('code', 'IF')->value('name'));
        $this->assertSame(54, Room::whereNull('faculty_id')->whereNotNull('code')->count());
        $this->assertSame(['Gedung E', 1], [Room::where('code', 'E101')->value('building'), Room::where('code', 'E101')->value('floor')]);
        $this->assertSame(6, Department::count());
        $this->assertSame(
            ['IF' => 'JTEIB', 'TL' => 'JTK', 'SL' => 'JTSP', 'TI' => 'JTI', 'TP' => 'JRI'],
            collect(['IF', 'TL', 'SL', 'TI', 'TP'])->mapWithKeys(fn (string $code) => [$code => StudyProgram::where('code', $code)->first()->department->code])->all(),
        );
        $this->assertSame(0, StudyProgram::whereNull('department_id')->count());
        $this->assertSame(
            [['07:30', '10:00'], ['10:20', '12:00'], ['13:00', '15:30'], ['16:00', '17:40']],
            TimeSlot::where('day', 1)->orderBy('session')->get()->map(fn (TimeSlot $slot) => [$slot->start_time, $slot->end_time])->all(),
        );
    }

    public function test_reseeding_keeps_edited_weights(): void
    {
        $this->seed(DatabaseSeeder::class);
        ConstraintType::where('code', ConstraintCode::SC_SKS)->update(['weight' => 5]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('5.00', ConstraintType::where('code', ConstraintCode::SC_SKS)->value('weight'));
        $this->assertSame(54, Room::count());
    }

    public function test_demo_master_data_covers_every_study_program(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(25, StudyProgram::where('constraint_status', ConstraintStatus::SUBMITTED)->count());
        $this->assertSame(0, StudyProgram::doesntHave('courses')->count());

        $informatics = StudyProgram::where('name', 'Informatika')->first();
        $this->assertSame(25, Course::where('study_program_id', $informatics->id)->count());
        $this->assertSame(44, CourseLecturer::whereRelation('course', 'study_program_id', $informatics->id)->count());

        $teaching = ['Ilmu Aktuaria' => 9, 'Bisnis Digital' => 9, 'Fisika' => 11, 'Informatika' => 16,
            'Matematika' => 8, 'Sistem Informasi' => 13, 'Statistika' => 9, 'Teknik Elektro' => 13];

        foreach ($teaching as $program => $count) {
            $this->assertSame($count, CourseLecturer::whereRelation('course.studyProgram', 'name', $program)->distinct()->count('lecturer_id'), $program);
        }

        // FPB, FRTI and Informatika carry real lecturers and courses; the other FSTI programs are still anonymized.
        $this->assertSame(286, Lecturer::count());
        $this->assertSame(80, Lecturer::where('name', 'like', 'Dosen % __')->count());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], Course::where('is_tpb', false)->distinct()->orderBy('semester')->pluck('semester')->all());
        $this->assertGreaterThan(0, LecturerPreference::count());
    }

    public function test_real_lecturers_teach_across_study_programs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $shipbuilding = StudyProgram::where('name', 'Teknik Perkapalan')->first();
        $this->assertSame(18, Lecturer::where('study_program_id', $shipbuilding->id)->count());

        // Teknik Sistem Perkapalan and Teknik Transportasi Laut are taught by Teknik Perkapalan lecturers.
        foreach (['Teknik Sistem Perkapalan', 'Teknik Transportasi Laut'] as $program) {
            $homes = CourseLecturer::whereRelation('course.studyProgram', 'name', $program)
                ->with('lecturer.studyProgram:id,name')->get()->pluck('lecturer.studyProgram.name')->unique()->values()->all();

            $this->assertContains('Teknik Perkapalan', $homes, $program);
        }

        $this->assertSame(0, LecturerPreference::whereIn('lecturer_id', Lecturer::whereIn('study_program_id', StudyProgram::whereIn('name', ['Teknik Industri', 'Teknik Mesin'])->pluck('id'))->pluck('id'))->count());
    }

    public function test_real_lecturers_get_accounts_from_their_email(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lecturer = Lecturer::where('name', 'like', 'Amanda Dwi Wantira%')->firstOrFail();

        $this->assertSame('dosen_Amanda@lecturer.itk.ac.id', $lecturer->user->email);
        $this->assertTrue($lecturer->user->hasRole(Role::LECTURER->value));
        // Every lecturer with an email in data/master.
        $this->assertSame(206, Lecturer::whereNotNull('user_id')->count());
    }

    public function test_demo_accounts_exist_for_every_faculty_and_study_program(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, User::query()->role(Role::FACULTY_ADMIN->value)->whereNotNull('faculty_id')->count());
        $this->assertSame(25, User::query()->role(Role::STUDY_PROGRAM_ADMIN->value)->distinct()->count('study_program_id'));
        $this->assertSame('Informatika', User::where('email', 'admin.if@example.test')->first()->studyProgram->name);
        $this->assertSame('Miftahul Fauzi', User::where('email', 'dosen.if01@lecturer.itk.ac.id')->first()->lecturer->name);
        $this->assertTrue(User::where('email', 'admin.tpb@example.test')->first()->hasRole(Role::TPB_ADMIN->value));
    }

    public function test_reseeding_demo_data_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [Lecturer::count(), Course::count(), CourseLecturer::count(), LecturerPreference::count(), User::count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [Lecturer::count(), Course::count(), CourseLecturer::count(), LecturerPreference::count(), User::count()]);
    }

    public function test_demo_runs_follow_the_program_flow_without_clashes(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Not part of DatabaseSeeder any more; run by hand when the review pages need sample runs.
        $this->seed(SchedulingRunSeeder::class);

        $fsti = Faculty::where('code', 'FSTI')->value('id');
        $fpb = Faculty::where('code', 'FPB')->value('id');
        $programs = fn (int $facultyId) => SchedulingRun::active()->where('faculty_id', $facultyId)->whereNotNull('study_program_id');
        $this->assertGreaterThan(0, $programs($fsti)->count());
        $this->assertSame(0, $programs($fsti)->whereNull('published_at')->count());
        $this->assertSame(0, $programs($fpb)->whereNotNull('published_at')->count());
        $this->assertSame(2 * $programs($fsti)->count(), SchedulingRun::where('faculty_id', $fsti)->count());
        $this->assertTrue(SchedulePublication::where('faculty_id', $fsti)->exists());
        $this->assertFalse(SchedulePublication::where('faculty_id', $fpb)->exists());
        $this->assertSame($programs($fpb)->count(), ProgramScheduleSubmission::where('status', 'submitted')->count());
        $this->assertSame(1, ScheduleDetail::where('is_overridden', true)->count());

        $active = ScheduleDetail::whereRelation('schedulingRun', 'is_active', true);
        $this->assertGreaterThan(0, (clone $active)->count());
        $duplicates = fn (array $columns) => (clone $active)->select($columns)->groupBy($columns)->havingRaw('count(*) > 1')->get()->count();
        $this->assertSame(0, $duplicates(['time_slot_id', 'lecturer_id']));
        $this->assertSame(0, (clone $active)->whereNotNull('room_id')->select(['time_slot_id', 'room_id'])->groupBy(['time_slot_id', 'room_id'])->havingRaw('count(*) > 1')->get()->count());

        // Every program meeting sits in a cell its faculty gave it.
        $outside = ScheduleDetail::query()->with('schedulingRun')->whereRelation('schedulingRun', fn ($q) => $q->active()->whereNotNull('study_program_id'))->get()
            ->reject(fn (ScheduleDetail $d) => RoomSlotAllocation::where('study_program_id', $d->schedulingRun->study_program_id)
                ->where('time_slot_id', $d->time_slot_id)->where('room_id', $d->room_id)->exists());
        $this->assertCount(0, $outside);

        // TPB first: one published TPB run, and every program run built against it.
        $tpbRun = SchedulingRun::whereNull('faculty_id')->sole();
        $this->assertNotNull($tpbRun->published_at);
        $this->assertSame(0, SchedulingRun::whereNotNull('faculty_id')->where('tpb_run_id', '!=', $tpbRun->id)->count());
        // Program genes keep out of the slots TPB takes from their program semester.
        $blocks = app(TpbBlockService::class)->blocks('2026/2027-GANJIL');
        $clash = ScheduleDetail::query()->with('course')->whereRelation('schedulingRun', fn ($q) => $q->active()->whereNotNull('faculty_id'))->get()
            ->filter(fn (ScheduleDetail $d) => in_array($d->time_slot_id, $blocks['programs'][$d->course->study_program_id][$d->course->semester] ?? [], true));
        $this->assertCount(0, $clash);

        $count = SchedulingRun::count();
        $this->seed(DatabaseSeeder::class);
        $this->seed(SchedulingRunSeeder::class);
        $this->assertSame($count, SchedulingRun::count());
    }

    public function test_tpb_demo_offerings_follow_the_institute_schedule(): void
    {
        $this->seed(DatabaseSeeder::class);

        $math = TpbOffering::query()->whereRelation('course', 'code', 'KU2512015')->where('period', '2026/2027-GANJIL')->sole();
        $this->assertSame(29, $math->parallel_class_count);
        $groups = $math->groups()->with('studyPrograms', 'courseLecturers')->orderBy('code')->get();
        $this->assertSame(['maba', 'maba', 'maba', 'maba', 'mengulang', 'campuran'], $groups->pluck('cohort.value')->all());
        $this->assertSame([1, 2, 3], $groups[0]->class_numbers);
        // Attending programs are dummy: as many as the source lists, at most six.
        $this->assertSame(4, $groups[0]->studyPrograms->count());
        $this->assertSame(6, $groups[4]->studyPrograms->count());
        $this->assertNull($groups[4]->studyPrograms->first()->pivot->target_semester);
        $this->assertSame(0, CourseLecturer::whereNotNull('tpb_offering_id')->whereNull('lecturer_id')->count());
    }
}
