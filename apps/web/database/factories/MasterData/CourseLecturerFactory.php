<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Course;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Lecturer;
use App\Models\MasterData\TpbGroup;
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

    /**
     * A class of a TPB group: course and offering follow the group.
     */
    public function forTpbGroup(TpbGroup $group): static
    {
        return $this->state(fn () => [
            'course_id' => $group->offering->course_id,
            'tpb_offering_id' => $group->tpb_offering_id,
            'tpb_group_id' => $group->id,
        ]);
    }
}
