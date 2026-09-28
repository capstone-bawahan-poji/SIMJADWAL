<?php

namespace Database\Seeders\Reference;

use App\Enums\Constraint\ConstraintCode;
use App\Models\Constraint\ConstraintType;
use Illuminate\Database\Seeder;

/**
 * firstOrCreate by code, so re-seeding never overwrites weights changed by a faculty admin.
 */
class ConstraintTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [ConstraintCode::HC1, 'Dosen mengajar sesuai dengan mata kuliah yang diampu', 10],
            [ConstraintCode::HC2, 'Satu dosen tidak boleh mengajar lebih dari satu kelas pada slot yang sama', 10],
            [ConstraintCode::HC3, 'Mata kuliah dengan prodi, semester, dan kelas yang sama tidak boleh dijadwalkan pada slot yang sama', 10],
            [ConstraintCode::HC4, 'Jumlah kelas satu prodi yang berjalan bersamaan pada satu slot tidak boleh melebihi batas kelas paralel', 10],
            [ConstraintCode::SC_INGIN, 'Dosen dijadwalkan pada slot yang diinginkan', 1],
            [ConstraintCode::SC_HINDARI, 'Dosen tidak dijadwalkan pada slot yang dihindari', 1],
            [ConstraintCode::SC_SKS, 'Mata kuliah 3 SKS pada slot tipe 3; mata kuliah 2 dan 4 SKS pada slot tipe 2', 2],
        ];

        foreach ($rows as [$code, $description, $weight]) {
            ConstraintType::query()->firstOrCreate(
                ['code' => $code],
                ['category' => $code->category(), 'description' => $description, 'weight' => $weight],
            );
        }
    }
}
