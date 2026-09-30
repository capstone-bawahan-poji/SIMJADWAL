<?php

namespace Database\Seeders\Reference;

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

    public function run(): void
    {
        foreach (self::FACULTIES as $facultyCode => ['name' => $facultyName, 'programs' => $programs]) {
            $faculty = Faculty::query()->firstOrCreate(['code' => $facultyCode], ['name' => $facultyName]);

            foreach ($programs as $programCode => $programName) {
                StudyProgram::query()->firstOrCreate(
                    ['code' => $programCode],
                    ['faculty_id' => $faculty->id, 'name' => $programName],
                );
            }
        }
    }
}
