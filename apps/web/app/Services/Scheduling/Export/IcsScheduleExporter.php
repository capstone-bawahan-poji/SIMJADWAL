<?php

namespace App\Services\Scheduling\Export;

use App\Exceptions\Shared\ApiException;
use App\Models\Scheduling\ScheduleDetail;
use App\Services\MasterData\AcademicPeriodService;
use Illuminate\Support\Carbon;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\RecurrenceFrequency;
use Spatie\IcalendarGenerator\ValueObjects\RRule;

/**
 * One weekly event per meeting, from the first lecture day of the period to the last
 * (academic_periods). Times are campus time (WITA). The UID depends on the gene and the
 * period only, so importing a newer file updates the events instead of duplicating them.
 */
readonly class IcsScheduleExporter implements ScheduleExporter
{
    public const TIMEZONE = 'Asia/Makassar';

    public function __construct(private AcademicPeriodService $periods) {}

    public function extension(): string
    {
        return 'ics';
    }

    public function contentType(): string
    {
        return 'text/calendar; charset=utf-8';
    }

    public function render(ScheduleExport $export): string
    {
        $period = $this->periods->findByCode($export->period)
            ?? throw ApiException::invalidState(__('The lecture dates of period :period are not set yet.', ['period' => $export->period]));

        $until = Carbon::parse($period->end_date->toDateString().' 23:59:59', self::TIMEZONE);
        $calendar = Calendar::create($export->title)->productIdentifier('-//SIMJADWAL//Jadwal Kuliah//ID');

        foreach ($export->details as $detail) {
            $calendar->event($this->event($detail, $period->start_date->toDateString(), $until, $export->period));
        }

        return $calendar->get();
    }

    private function event(ScheduleDetail $d, string $firstDay, Carbon $until, string $period): Event
    {
        $date = Carbon::parse($firstDay, self::TIMEZONE);
        while ($date->dayOfWeekIso !== $d->timeSlot->day->value) {
            $date->addDay();
        }
        $at = fn (string $time) => Carbon::parse($date->toDateString().' '.$time, self::TIMEZONE);

        return Event::create(ScheduleLine::course($d))
            ->uniqueIdentifier("schedule-detail-{$d->id}-".str_replace('/', '-', $period).'@simjadwal')
            ->startsAt($at($d->timeSlot->start_time))
            ->endsAt($at($d->timeSlot->end_time))
            ->address(ScheduleLine::room($d))
            ->description(implode("\n", array_filter([
                __('Lecturer').': '.ScheduleLine::lecturer($d),
                $d->course->studyProgram ? __('Study Program').': '.$d->course->studyProgram->name : null,
                __('Meeting').' '.$d->meeting_number.' · '.$d->course->sks.' SKS',
            ])))
            ->rrule(RRule::frequency(RecurrenceFrequency::Weekly)->until($until));
    }
}
