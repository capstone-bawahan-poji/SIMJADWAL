<?php

namespace Tests\Feature\Database;

use App\Enums\Account\Role;
use App\Enums\Constraint\ConstraintCode;
use App\Enums\Constraint\ConstraintStatus;
use App\Models\Constraint\ConstraintType;
use App\Models\Constraint\LecturerPreference;
use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_matches_database_md(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Faculty::count());
        $this->assertSame(26, StudyProgram::count());
        $this->assertSame(20, TimeSlot::count());
        $this->assertSame(54, Room::count());
        $this->assertSame(7, ConstraintType::count());
        $this->assertTrue(User::where('email', 'superadmin@penjadwalan.test')->first()->hasRole('superadmin'));
    }

    /**
     * Re-seeding must not overwrite constraint weights a faculty admin already changed.
     */
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

        $this->assertSame(26, StudyProgram::where('constraint_status', ConstraintStatus::SUBMITTED)->count());
        $this->assertSame(0, StudyProgram::doesntHave('courses')->count());

        $informatics = StudyProgram::where('name', 'Informatika')->first();
        $this->assertSame(26, Course::where('study_program_id', $informatics->id)->count());
        $this->assertSame(51, CourseLecturer::whereRelation('course', 'study_program_id', $informatics->id)->count());

        // Lecturers teaching in each program with real data, same as the source course list.
        $teaching = ['Ilmu Aktuaria' => 9, 'Bisnis Digital' => 9, 'Fisika' => 11, 'Informatika' => 13,
            'Matematika' => 8, 'Sistem Informasi' => 13, 'Statistika' => 9, 'Teknik Elektro' => 12];

        foreach ($teaching as $program => $count) {
            $this->assertSame($count, CourseLecturer::whereRelation('course.studyProgram', 'name', $program)->distinct()->count('lecturer_id'), $program);
        }

        $this->assertSame(263, Lecturer::count());
        $this->assertTrue(Lecturer::pluck('name')->every(fn (string $name) => preg_match('/^Dosen .+ \d{2}$/', $name) === 1));
        $this->assertSame([1, 3, 5, 7], Course::distinct()->orderBy('semester')->pluck('semester')->all());
        $this->assertGreaterThan(0, LecturerPreference::count());
    }

    public function test_demo_accounts_exist_for_every_faculty_and_study_program(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, User::query()->role(Role::FACULTY_ADMIN->value)->whereNotNull('faculty_id')->count());
        $this->assertSame(26, User::query()->role(Role::STUDY_PROGRAM_ADMIN->value)->distinct()->count('study_program_id'));
        $this->assertSame('Informatika', User::where('email', 'admin.if@penjadwalan.test')->first()->studyProgram->name);
        $this->assertSame('IF01', User::where('email', 'dosen.if01@penjadwalan.test')->first()->lecturer->code);
    }

    public function test_reseeding_demo_data_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [Lecturer::count(), Course::count(), CourseLecturer::count(), LecturerPreference::count(), User::count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [Lecturer::count(), Course::count(), CourseLecturer::count(), LecturerPreference::count(), User::count()]);
    }
}
