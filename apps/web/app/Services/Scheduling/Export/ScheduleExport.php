<?php

namespace App\Services\Scheduling\Export;

use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything an exporter needs: the genes, ordered by slot, and how to title the file.
 */
readonly class ScheduleExport
{
    /**
     * @param  Collection<int, ScheduleDetail>  $details  with ScheduleDetailTransformer::relations()
     * @param  Collection<int, TimeSlot>  $timeSlots  every slot, ordered by day and session
     */
    public function __construct(
        public string $period,
        public string $title,
        public string $subtitle,
        public string $filename,
        public bool $isDraft,
        public Collection $details,
        public Collection $timeSlots,
        public Carbon $generatedAt,
    ) {}

    public function matrix(): ScheduleMatrix
    {
        return new ScheduleMatrix($this->details, $this->timeSlots);
    }
}
