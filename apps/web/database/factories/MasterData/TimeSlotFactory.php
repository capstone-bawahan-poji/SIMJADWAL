<?php

namespace Database\Factories\MasterData;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pick day and session explicitly when creating several slots: (day, session) is unique.
 *
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'day' => Day::MONDAY,
            'session' => 1,
            'start_time' => '08:00',
            'end_time' => '10:30',
            'type' => 3,
        ];
    }
}
