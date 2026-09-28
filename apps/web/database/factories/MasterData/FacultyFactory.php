<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Fakultas '.fake()->unique()->words(3, true),
        ];
    }
}
