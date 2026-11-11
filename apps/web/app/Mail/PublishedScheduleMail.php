<?php

namespace App\Mail;

use App\Services\Scheduling\Period;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The personal class schedule a student asked for on the landing page.
 * Built inside the queued job, so the attachments are rendered at send time.
 */
class PublishedScheduleMail extends Mailable
{
    /**
     * @param  list<array{content: string, content_type: string, filename: string}>  $files
     * @param  list<array{course_id: int, name: string, code: string, sks: int, class_label: string, day: string, time: string, room: string}>  $lines
     */
    public function __construct(public readonly string $period, public readonly array $files, public readonly array $lines = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Class schedule :period', ['period' => Period::label($this->period)]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.published-schedule',
            text: 'mail.published-schedule-text',
            with: [
                'lines' => $this->lines,
                'courseCount' => collect($this->lines)->unique('course_id')->count(),
                'totalSks' => collect($this->lines)->unique('course_id')->sum('sks'),
                'periodLabel' => Period::label($this->period),
                'formats' => array_map(fn (array $file) => pathinfo($file['filename'], PATHINFO_EXTENSION), $this->files),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $file) => Attachment::fromData(fn () => $file['content'], $file['filename'])->withMime($file['content_type']),
            $this->files,
        );
    }
}
