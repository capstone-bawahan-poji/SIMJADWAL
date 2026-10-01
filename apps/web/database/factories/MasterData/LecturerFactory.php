<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Lecturer;
use App\Models\MasterData\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lecturer>
 */
class LecturerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'study_program_id' => StudyProgram::factory(),
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'),
            'name' => fake()->name(),
            'title' => fake()->randomElement(['S.Kom., M.Kom.', 'S.T., M.T.', 'Dr.', null]),
        ];
    }
}
