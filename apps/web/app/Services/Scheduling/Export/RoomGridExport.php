<?php

namespace App\Services\Scheduling\Export;

use Illuminate\Support\Carbon;

readonly class RoomGridExport
{
    public function __construct(
        public string $title,
        public string $subtitle,
        public string $filename,
        public RoomGrid $grid,
        public Carbon $generatedAt,
    ) {}
}
