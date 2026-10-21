<?php

namespace Database\Seeders\Reference;

use App\Models\MasterData\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $defaultCapacity = config('seeding.room_capacity');
        $rows = json_decode(file_get_contents(database_path('seeders/data/reference/rooms.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $code = is_array($row) ? $row['code'] : $row;
            $room = Room::query()->firstOrCreate(
                ['code' => $code],
                ['faculty_id' => null, 'capacity' => is_array($row) ? $row['capacity'] : $defaultCapacity],
            );

            if ($room->building === null && preg_match('/^([A-Z])(\d)\d{2}$/', $code, $match)) {
                $room->update(['building' => "Gedung {$match[1]}", 'floor' => (int) $match[2]]);
            }
        }
    }
}
