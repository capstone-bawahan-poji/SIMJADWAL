<?php

namespace App\Services\Scheduling;

use App\Models\MasterData\CourseLecturer;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulePublication;
use App\Models\Scheduling\SchedulingRun;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The final schedule as other campus systems read it: published faculty schedules and the
 * published TPB schedule. Rows are flat and keyed by codes (course code, class label, lecturer
 * NIP, room code), never by internal ids, so the contract survives a database rebuild.
 */
readonly class PublishedScheduleService
{
    public const COLUMNS = [
        'period', 'faculty_code', 'study_program_code', 'course_code', 'course_name', 'sks', 'semester',
        'class_label', 'meeting_number', 'lecturer_nip', 'lecturer_name', 'day', 'session', 'start_time',
        'end_time', 'room_code', 'room_capacity', 'is_tpb',
    ];

    /**
     * @param  array{faculty_code?: string|null, study_program_code?: string|null, updated_since?: string|null}  $filters
     * @return CursorPaginator<int, array<string, mixed>>
     */
    public function rows(string $period, array $filters, int $perPage): CursorPaginator
    {
        return $this->query($period, $filters)
            ->cursorPaginate($perPage)
            ->through(fn (ScheduleDetail $d) => $this->row($d, $period));
    }

    /**
     * Every row, for the file export and the checksum.
     *
     * @param  array{faculty_code?: string|null, study_program_code?: string|null, updated_since?: string|null}  $filters
     * @return iterable<array<string, mixed>>
     */
    public function all(string $period, array $filters): iterable
    {
        foreach ($this->query($period, $filters)->lazyById(500, 'schedule_details.id', 'id') as $detail) {
            yield $this->row($detail, $period);
        }
    }

    /**
     * Fingerprint of the published rows of one faculty, stored with its publication.
     */
    public function checksum(int $facultyId, string $period): string
    {
        $context = hash_init('sha256');
        $this->query($period, [])
            ->whereRelation('schedulingRun', 'faculty_id', $facultyId)
            ->lazyById(500, 'schedule_details.id', 'id')
            ->each(fn (ScheduleDetail $d) => hash_update($context, json_encode($this->row($d, $period), JSON_THROW_ON_ERROR)));

        return 'sha256:'.hash_final($context);
    }

    /**
     * Published faculties of the period, plus TPB, with what changes when they publish again.
     *
     * @return list<array<string, mixed>>
     */
    public function publications(string $period): array
    {
        $tpb = SchedulingRun::query()->active()->whereNull('faculty_id')->whereNotNull('published_at')->where('period', $period)->first();

        return SchedulePublication::query()->with('faculty:id,code,name')->where('period', $period)->orderBy('faculty_id')->get()
            ->map(fn (SchedulePublication $p) => [
                'scope' => 'faculty',
                'faculty' => ['code' => $p->faculty->code, 'name' => $p->faculty->name],
                'published_at' => $p->published_at->toIso8601String(),
                'checksum' => $p->checksum,
            ])
            ->when($tpb !== null, fn ($rows) => $rows->prepend([
                'scope' => 'tpb',
                'faculty' => null,
                'published_at' => $tpb->published_at->toIso8601String(),
                'checksum' => 'run:'.$tpb->id,
            ]))
            ->values()->all();
    }

    /**
     * ETag of a listing: changes whenever a matching publication does.
     *
     * @param  array{faculty_code?: string|null}  $filters
     */
    public function etag(string $period, array $filters): string
    {
        $parts = collect($this->publications($period))
            ->filter(fn (array $p) => empty($filters['faculty_code']) || $p['scope'] === 'tpb' || $p['faculty']['code'] === $filters['faculty_code'])
            ->map(fn (array $p) => $p['checksum'])
            ->implode('|');

        return '"'.hash('sha256', $period.'|'.json_encode($filters).'|'.$parts).'"';
    }

    /**
     * @param  array{faculty_code?: string|null, study_program_code?: string|null, updated_since?: string|null}  $filters
     * @return Builder<ScheduleDetail>
     */
    private function query(string $period, array $filters): Builder
    {
        return ScheduleDetail::query()
            ->with([
                'schedulingRun:id,faculty_id,study_program_id,published_at',
                'schedulingRun.faculty:id,code',
                'schedulingRun.studyProgram:id,code',
                'course:id,code,name,sks,semester,is_tpb',
                'lecturer:id,nip,name,title',
                'timeSlot:id,day,session,start_time,end_time',
                'room:id,code,capacity',
            ])
            ->whereHas('schedulingRun', fn (Builder $q) => $q->where('is_active', true)->where('period', $period)->whereNotNull('published_at')
                ->when($filters['updated_since'] ?? null, fn (Builder $q, string $since) => $q->where('published_at', '>=', $since))
                ->when($filters['faculty_code'] ?? null, fn (Builder $q, string $code) => $q->whereRelation('faculty', 'code', $code))
                ->when($filters['study_program_code'] ?? null, fn (Builder $q, string $code) => $q->whereRelation('studyProgram', 'code', $code)))
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ScheduleDetail $d, string $period): array
    {
        $run = $d->schedulingRun;

        return [
            'period' => $period,
            'faculty_code' => $run->faculty?->code,
            'study_program_code' => $run->studyProgram?->code,
            'course_code' => $d->course->code,
            'course_name' => $d->course->name,
            'sks' => $d->course->sks,
            'semester' => $d->course->semester,
            'class_label' => CourseLecturer::labelFor($d->class_number),
            'meeting_number' => $d->meeting_number,
            'lecturer_nip' => $d->lecturer->nip,
            'lecturer_name' => $d->lecturer->name,
            'day' => $d->timeSlot->day->value,
            'session' => $d->timeSlot->session,
            'start_time' => $d->timeSlot->start_time,
            'end_time' => $d->timeSlot->end_time,
            'room_code' => $d->room?->code,
            'room_capacity' => $d->room?->capacity,
            'is_tpb' => $d->course->is_tpb,
        ];
    }
}
