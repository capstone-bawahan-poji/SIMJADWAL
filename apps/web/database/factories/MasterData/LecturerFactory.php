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
            'nip' => fake()->unique()->numerify('19##########0#####'),
            'name' => fake()->name(),
            'title' => fake()->randomElement(['S.Kom., M.Kom.', 'S.T., M.T.', 'Dr.', null]),
        ];
    }
}
