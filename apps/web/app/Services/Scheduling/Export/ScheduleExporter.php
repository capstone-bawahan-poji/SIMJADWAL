<?php

namespace App\Services\Scheduling\Export;

interface ScheduleExporter
{
    public function extension(): string;

    public function contentType(): string;

    /**
     * File content.
     */
    public function render(ScheduleExport $export): string;
}
