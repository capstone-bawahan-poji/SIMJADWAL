<?php

namespace App\Jobs;

use App\Mail\PublishedScheduleMail;
use App\Models\MasterData\CourseLecturer;
use App\Services\Scheduling\Export\ScheduleExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPublishedSchedule implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{course_id: int, class_number: int|null}>  $classes
     * @param  list<string>  $formats
     */
    public function __construct(
        public readonly string $email,
        public readonly string $period,
        public readonly array $classes,
        public readonly array $formats,
    ) {}

    public function handle(ScheduleExportService $exportService): void
    {
        $filters = ['classes' => $this->classes];

        $files = array_map(
            fn (string $format) => $exportService->exportPublished($this->period, $filters, $format),
            $this->formats,
        );

        Mail::to($this->email)->send(new PublishedScheduleMail($this->period, $files, $this->lines($exportService)));
    }

    /**
     * One line per meeting, ordered by day then time, as the email body lists them.
     *
     * @return list<array{course_id: int, name: string, code: string, sks: int, class_label: string, day: string, time: string, room: string}>
     */
    private function lines(ScheduleExportService $exportService): array
    {
        return $exportService->publishedDetails($this->period, ['classes' => $this->classes])
            ->sortBy([
                fn ($a, $b) => $a->timeSlot->day->value <=> $b->timeSlot->day->value,
                fn ($a, $b) => $a->timeSlot->start_time <=> $b->timeSlot->start_time,
                fn ($a, $b) => $a->course->code <=> $b->course->code,
            ])
            ->map(fn ($d) => [
                'course_id' => $d->course_id,
                'name' => $d->course->name,
                'code' => $d->course->code,
                'sks' => $d->course->sks,
                'class_label' => CourseLecturer::labelFor($d->class_number),
                'day' => $d->timeSlot->day->label(),
                'time' => $d->timeSlot->start_time.'-'.$d->timeSlot->end_time,
                // Not every gene has a room yet; the mail shows "-" until the faculty assigns one.
                'room' => $d->room?->code ?? '-',
            ])
            ->values()
            ->all();
    }
}
