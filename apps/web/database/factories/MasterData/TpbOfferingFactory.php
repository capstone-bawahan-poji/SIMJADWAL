<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\TpbOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TpbOffering>
 */
class TpbOfferingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->tpb(),
            'period' => '2026/2027-GANJIL',
            'parallel_class_count' => 4,
            'class_capacity' => 40,
        ];
    }
}
