<?php

namespace App\Services\Scheduling\Export;

interface RoomGridExporter
{
    public function extension(): string;

    public function contentType(): string;

    public function render(RoomGridExport $export): string;
}
