<?php

namespace Database\Factories\MasterData;

use App\Enums\Constraint\ConstraintStatus;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyProgram>
 */
class StudyProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]'),
            'name' => 'Program '.fake()->unique()->words(3, true),
            'constraint_status' => ConstraintStatus::DRAFT,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'constraint_status' => ConstraintStatus::SUBMITTED,
            'constraint_submitted_at' => now(),
        ]);
    }
}
