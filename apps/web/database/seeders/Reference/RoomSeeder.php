<?php

namespace Database\Seeders\Reference;

use App\Models\MasterData\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $capacity = config('seeding.room_capacity');
        $names = json_decode(file_get_contents(database_path('seeders/data/reference/rooms.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($names as $name) {
            Room::query()->firstOrCreate(
                ['name' => $name],
                ['faculty_id' => null, 'capacity' => $capacity],
            );
        }
    }
}
