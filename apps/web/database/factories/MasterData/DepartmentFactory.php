<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'code' => fake()->unique()->regexify('J[A-Z]{4}'),
            'name' => 'Jurusan '.fake()->unique()->words(3, true),
        ];
    }
}
