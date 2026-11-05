<?php

namespace App\Services\Scheduling\Export;

use App\Enums\MasterData\Day;
use App\Models\MasterData\TimeSlot;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Support\Collection;

/**
 * The week as a grid: one row per session, one column per weekday, each cell the genes in that slot.
 * Days without any slot are left out. Sessions keep the times of their first slot as the row label;
 * a cell whose slot has other times shows them itself (ScheduleMatrix::cellTime()).
 */
readonly class ScheduleMatrix
{
    /** @var list<Day> */
    public array $days;

    /** @var array<int, string> session => "07:30–10:00" */
    public array $sessions;

    /** @var array<int, array<int, list<ScheduleDetail>>> session => day => genes */
    private array $cells;

    /** @var array<int, array<int, TimeSlot>> session => day => slot */
    private array $slots;

    /**
     * @param  Collection<int, ScheduleDetail>  $details
     * @param  Collection<int, TimeSlot>  $timeSlots
     */
    public function __construct(Collection $details, Collection $timeSlots)
    {
        $slots = [];
        $sessions = [];
        foreach ($timeSlots as $slot) {
            $slots[$slot->session][$slot->day->value] = $slot;
            $sessions[$slot->session] ??= self::range($slot);
        }
        ksort($sessions);

        $cells = [];
        foreach ($details as $detail) {
            $cells[$detail->timeSlot->session][$detail->timeSlot->day->value][] = $detail;
        }

        $this->slots = $slots;
        $this->sessions = $sessions;
        $this->cells = $cells;
        $this->days = array_values(array_filter(Day::cases(), fn (Day $day) => $timeSlots->contains(fn (TimeSlot $s) => $s->day === $day)));
    }

    /**
     * @return list<ScheduleDetail>
     */
    public function cell(int $session, Day $day): array
    {
        return $this->cells[$session][$day->value] ?? [];
    }

    /**
     * Times of the slot when they differ from the row label, else null.
     */
    public function cellTime(int $session, Day $day): ?string
    {
        $slot = $this->slots[$session][$day->value] ?? null;

        return $slot !== null && self::range($slot) !== $this->sessions[$session] ? self::range($slot) : null;
    }

    public static function range(TimeSlot $slot): string
    {
        return $slot->start_time.'–'.$slot->end_time;
    }
}
