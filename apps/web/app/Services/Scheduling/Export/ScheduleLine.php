<?php

namespace App\Services\Scheduling\Export;

use App\Models\MasterData\CourseLecturer;
use App\Models\Scheduling\ScheduleDetail;

/**
 * Text pieces of one gene, the same in every format.
 */
final class ScheduleLine
{
    public static function course(ScheduleDetail $d): string
    {
        return $d->course->code.' '.$d->course->name.' ('.CourseLecturer::labelFor($d->class_number).')';
    }

    public static function lecturer(ScheduleDetail $d): string
    {
        return $d->lecturer->name;
    }

    public static function room(ScheduleDetail $d): string
    {
        return $d->room?->code ?? __('No room yet');
    }
}
