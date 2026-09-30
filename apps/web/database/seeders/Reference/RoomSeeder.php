<?php

namespace Database\Seeders\Reference;

use App\Models\MasterData\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $capacity = config('seeding.room_capacity');
        $codes = json_decode(file_get_contents(database_path('seeders/data/reference/rooms.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($codes as $code) {
            Room::query()->firstOrCreate(
                ['code' => $code],
                ['faculty_id' => null, 'capacity' => $capacity],
            );
        }
    }
}
