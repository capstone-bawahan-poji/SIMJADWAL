<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pick the code explicitly when creating several periods: code is unique.
 *
 * @extends Factory<AcademicPeriod>
 */
class AcademicPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => '2026/2027-GANJIL',
            'start_date' => '2026-08-31',
            'end_date' => '2026-12-18',
        ];
    }
}
