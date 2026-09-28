<?php

namespace Database\Seeders\Reference;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = json_decode(file_get_contents(database_path('seeders/data/reference/time_slots.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($slots as $slot) {
            TimeSlot::query()->updateOrCreate(
                ['day' => Day::from($slot['day']), 'session' => $slot['session']],
                ['start_time' => $slot['start'], 'end_time' => $slot['end'], 'type' => $slot['type']],
            );
        }
    }
}
