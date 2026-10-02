<?php

namespace Database\Seeders\Reference;

use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use Illuminate\Database\Seeder;

class FacultyStudyProgramSeeder extends Seeder
{
    /**
     * Faculty code => name and programs (program code => name). Program codes match the
     * `prefix` in data/master and the prefix of their course codes (IF2514201).
     */
    private const FACULTIES = [
        'FSTI' => [
            'name' => 'Fakultas Sains dan Teknologi Informasi',
            'programs' => [
                'MA' => 'Matematika',
                'AK' => 'Ilmu Aktuaria',
                'ST' => 'Statistika',
                'FI' => 'Fisika',
                'IF' => 'Informatika',
                'SI' => 'Sistem Informasi',
                'BD' => 'Bisnis Digital',
                'TE' => 'Teknik Elektro',
                'TB' => 'Teknik Biomedis',
            ],
        ],
        'FPB' => [
            'name' => 'Fakultas Pembangunan Berkelanjutan',
            'programs' => [
                'PK' => 'Teknik Perkapalan',
                'KL' => 'Teknik Kelautan',
                'TL' => 'Teknik Lingkungan',
                'SP' => 'Teknik Sistem Perkapalan',
                'TT' => 'Teknik Transportasi Laut',
                'SL' => 'Teknik Sipil',
                'PW' => 'Perencanaan Wilayah dan Kota',
                'AR' => 'Arsitektur',
                'DK' => 'Desain Komunikasi Visual',
                'GM' => 'Teknik Geomatika',
            ],
        ],
        'FRTI' => [
            'name' => 'Fakultas Rekayasa dan Teknologi Industri',
            'programs' => [
                'MS' => 'Teknik Mesin',
                'TI' => 'Teknik Industri',
                'LG' => 'Teknik Logistik',
                'MM' => 'Teknik Material dan Metalurgi',
                'TP' => 'Teknologi Pangan',
                'TK' => 'Teknik Kimia',
                'RK' => 'Rekayasa Keselamatan',
            ],
        ],
    ];

    /**
     * Faculty code => departments (jurusan code => name and program codes), following the ITK
     * program pages. Department codes are local abbreviations.
     */
    private const DEPARTMENTS = [
        'FSTI' => [
            'JSAD' => ['name' => 'Jurusan Sains dan Analitika Data', 'programs' => ['MA', 'AK', 'ST', 'FI']],
            'JTEIB' => ['name' => 'Jurusan Teknik Elektro, Informatika, dan Bisnis', 'programs' => ['IF', 'SI', 'BD', 'TE', 'TB']],
        ],
        'FPB' => [
            'JTK' => ['name' => 'Jurusan Teknologi Kemaritiman', 'programs' => ['PK', 'KL', 'TL', 'SP', 'TT']],
            'JTSP' => ['name' => 'Jurusan Teknik Sipil dan Perencanaan', 'programs' => ['SL', 'PW', 'AR', 'DK', 'GM']],
        ],
        'FRTI' => [
            'JTI' => ['name' => 'Jurusan Teknologi Industri', 'programs' => ['MS', 'TI', 'LG', 'MM']],
            'JRI' => ['name' => 'Jurusan Rekayasa Industri', 'programs' => ['TP', 'TK', 'RK']],
        ],
    ];

    public function run(): void
    {
        foreach (self::FACULTIES as $facultyCode => ['name' => $facultyName, 'programs' => $programs]) {
            $faculty = Faculty::query()->firstOrCreate(['code' => $facultyCode], ['name' => $facultyName]);
            $departmentIds = $this->seedDepartments($faculty, self::DEPARTMENTS[$facultyCode] ?? []);

            foreach ($programs as $programCode => $programName) {
                $program = StudyProgram::query()->firstOrCreate(
                    ['code' => $programCode],
                    ['faculty_id' => $faculty->id, 'name' => $programName],
                );

                // Fill only an empty department, so a department set by an admin survives re-seeding.
                if ($program->department_id === null && isset($departmentIds[$programCode])) {
                    $program->update(['department_id' => $departmentIds[$programCode]]);
                }
            }
        }
    }

    /**
     * @param  array<string, array{name: string, programs: list<string>}>  $departments
     * @return array<string, int> program code => department id
     */
    private function seedDepartments(Faculty $faculty, array $departments): array
    {
        $ids = [];

        foreach ($departments as $code => ['name' => $name, 'programs' => $programCodes]) {
            $department = Department::query()->firstOrCreate(['code' => $code], ['faculty_id' => $faculty->id, 'name' => $name]);

            foreach ($programCodes as $programCode) {
                $ids[$programCode] = $department->id;
            }
        }

        return $ids;
    }
}
