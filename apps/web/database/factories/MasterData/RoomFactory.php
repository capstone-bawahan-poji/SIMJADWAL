<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'code' => fake()->unique()->regexify('[A-Z][1-4]0[1-9]'),
            'name' => null,
            'capacity' => 40,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => ['faculty_id' => null]);
    }
}
