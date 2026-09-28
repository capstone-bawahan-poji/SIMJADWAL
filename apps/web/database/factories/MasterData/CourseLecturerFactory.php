<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLecturer>
 */
class CourseLecturerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'class_number' => 1,
            'lecturer_id' => Lecturer::factory(),
        ];
    }
}
