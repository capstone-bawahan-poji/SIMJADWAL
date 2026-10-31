<?php

namespace Database\Seeders\Reference;

use App\Models\MasterData\AcademicPeriod;
use Illuminate\Database\Seeder;

/**
 * Example lecture dates. Superadmin corrects them from the academic calendar; a re-run keeps the edits.
 */
class AcademicPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [
            '2026/2027-GANJIL' => ['2026-08-31', '2026-12-18'],
            '2026/2027-GENAP' => ['2027-02-08', '2027-06-04'],
        ];

        foreach ($periods as $code => [$start, $end]) {
            AcademicPeriod::query()->firstOrCreate(['code' => $code], ['start_date' => $start, 'end_date' => $end]);
        }
    }
}
