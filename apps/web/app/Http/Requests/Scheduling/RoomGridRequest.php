<?php

namespace App\Http\Requests\Scheduling;

class RoomGridRequest extends ExportRoomGridRequest
{
    protected function formatRules(): array
    {
        return [];
    }
}
