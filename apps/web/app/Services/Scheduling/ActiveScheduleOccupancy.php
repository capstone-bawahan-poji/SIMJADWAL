<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Support\Collection;

/**
 * Which lecturer and room each slot already holds in a set of active genes.
 * The single in-memory view behind override checks and activation clash checks.
 */
class ActiveScheduleOccupancy
{
    /** @var array<int, array<int, ScheduleDetail>> slot => lecturer_id => gene */
    private array $lecturerAt = [];

    /** @var array<int, array<int, ScheduleDetail>> slot => room_id => gene */
    private array $roomAt = [];

    /**
     * @param  Collection<int, ScheduleDetail>  $genes  with course:id,name loaded
     */
    public function __construct(Collection $genes)
    {
        foreach ($genes as $gene) {
            $this->lecturerAt[$gene->time_slot_id][$gene->lecturer_id] ??= $gene;
            if ($gene->room_id !== null) {
                $this->roomAt[$gene->time_slot_id][$gene->room_id] ??= $gene;
            }
        }
    }

    public function lecturerClash(int $slotId, int $lecturerId): ?ScheduleDetail
    {
        return $this->lecturerAt[$slotId][$lecturerId] ?? null;
    }

    public function roomClash(int $slotId, ?int $roomId): ?ScheduleDetail
    {
        return $roomId === null ? null : ($this->roomAt[$slotId][$roomId] ?? null);
    }
}