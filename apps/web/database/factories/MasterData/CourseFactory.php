<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'study_program_id' => StudyProgram::factory(),
            'code' => fake()->unique()->regexify('[A-Z]{2}[0-9]{4}'),
            'name' => ucwords(fake()->words(3, true)),
            'sks' => fake()->randomElement([2, 3, 4]),
            'semester' => fake()->numberBetween(1, 8),
            'parallel_class_count' => 1,
            'class_capacity' => 40,
        ];
    }
}
