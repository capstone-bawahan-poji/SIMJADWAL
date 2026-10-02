<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\TpbGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TpbGroup>
 */
class TpbGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->tpb(),
            'code' => fake()->unique()->regexify('G[0-9]{3}'),
            'time_slot_id' => null,
        ];
    }
}
