<?php

namespace Database\Seeders\Reference;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use Illuminate\Database\Seeder;

class FacultyStudyProgramSeeder extends Seeder
{
    private const FACULTIES = [
        'Fakultas Sains dan Teknologi Informasi' => [
            'Matematika',
            'Ilmu Aktuaria',
            'Statistika',
            'Fisika',
            'Informatika',
            'Sistem Informasi',
            'Bisnis Digital',
            'Teknik Elektro',
            'Teknik Biomedis',
        ],
        'Fakultas Pembangunan Berkelanjutan' => [
            'Teknik Perkapalan',
            'Teknik Kelautan',
            'Teknik Lingkungan',
            'Teknik Sistem Perkapalan',
            'Teknik Transportasi Laut',
            'Teknik Sipil',
            'Perencanaan Wilayah dan Kota',
            'Arsitektur',
            'Desain Komunikasi Visual',
            'Teknik Geomatika',
        ],
        'Fakultas Rekayasa dan Teknologi Industri' => [
            'Teknik Mesin',
            'Teknik Industri',
            'Teknik Logistik',
            'Teknik Material dan Metalurgi',
            'Teknologi Pangan',
            'Teknik Kimia',
            'Rekayasa Keselamatan',
        ],
    ];

    public function run(): void
    {
        foreach (self::FACULTIES as $facultyName => $programNames) {
            $faculty = Faculty::query()->firstOrCreate(['name' => $facultyName]);

            foreach ($programNames as $programName) {
                StudyProgram::query()->firstOrCreate(
                    ['name' => $programName],
                    ['faculty_id' => $faculty->id],
                );
            }
        }
    }
}
