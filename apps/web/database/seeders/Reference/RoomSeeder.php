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
            $room = Room::query()->firstOrCreate(
                ['code' => $code],
                ['faculty_id' => null, 'capacity' => $capacity],
            );

            // Campus codes read as building letter + floor + number: E101 = Gedung E, lantai 1.
            // Only fill empty values, so an admin's edits survive re-seeding.
            if ($room->building === null && preg_match('/^([A-Z])(\d)\d{2}$/', $code, $match)) {
                $room->update(['building' => "Gedung {$match[1]}", 'floor' => (int) $match[2]]);
            }
        }
    }
}
