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

    public static function courseTitle(ScheduleDetail $d): string
    {
        return $d->course->code.' '.$d->course->name;
    }

    /**
     * Genes of one cell grouped by course, so parallel classes of one course share a heading.
     *
     * @param  list<ScheduleDetail>  $details
     * @return list<array{title: string, details: list<ScheduleDetail>}>
     */
    public static function groupByCourse(array $details): array
    {
        $groups = [];
        foreach ($details as $d) {
            $groups[$d->course_id] ??= ['title' => self::courseTitle($d), 'details' => []];
            $groups[$d->course_id]['details'][] = $d;
        }

        return array_values($groups);
    }

    /**
     * Class letters of a cell group, consecutive letters folded: "A-E, G". Hyphen on purpose (ranges).
     *
     * @param  list<ScheduleDetail>  $details
     */
    public static function classRange(array $details): string
    {
        $numbers = collect($details)->pluck('class_number')->unique()->sort()->values()->all();
        $parts = [];
        for ($i = 0; $i < count($numbers); $i++) {
            $start = $numbers[$i];
            while (isset($numbers[$i + 1]) && $numbers[$i + 1] === $numbers[$i] + 1) {
                $i++;
            }
            $end = $numbers[$i];
            $parts[] = $start === $end
                ? CourseLecturer::labelFor($start)
                : CourseLecturer::labelFor($start).($end - $start === 1 ? ', ' : '-').CourseLecturer::labelFor($end);
        }

        return implode(', ', $parts);
    }

    public static function classLine(ScheduleDetail $d): string
    {
        return __('Class').' '.CourseLecturer::labelFor($d->class_number).' · '.self::lecturer($d).' · '.self::room($d);
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
