<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\SubmissionStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\SchedulePublication;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * POS step 10: the faculty publishes the schedules of all its programs at once. Every program with
 * a course to schedule in the period must be accepted. A publication is final: nothing in the
 * faculty's schedule moves afterwards. Only superadmin can withdraw one (with a reason), e.g.
 * after a wrong entry.
 */
readonly class SchedulePublicationService
{
    public function __construct(
        private DatabaseManager $db,
        private PublishedScheduleService $published,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(Faculty $faculty, string $period): array
    {
        $publication = SchedulePublication::query()->with('publishedBy:id,name')
            ->where('faculty_id', $faculty->id)->where('period', $period)->first();
        $waiting = $this->unaccepted($faculty, $period);

        return [
            'period' => $period,
            'faculty' => ['id' => $faculty->id, 'code' => $faculty->code, 'name' => $faculty->name],
            'published' => $publication !== null,
            'published_at' => $publication?->published_at->toIso8601String(),
            'published_by' => $publication?->publishedBy ? ['id' => $publication->publishedBy->id, 'name' => $publication->publishedBy->name] : null,
            'checksum' => $publication?->checksum,
            'can_publish' => $publication === null && $waiting === [] && $this->accepted($faculty, $period) !== [],
            'waiting_programs' => $waiting,
        ];
    }

    public function publish(User $actor, Faculty $faculty, string $period): array
    {
        $this->db->transaction(function () use ($actor, $faculty, $period) {
            // Serialises publications and the review steps of the faculty in the period.
            $submissions = ProgramScheduleSubmission::query()->with('schedulingRun')
                ->whereIn('study_program_id', StudyProgram::query()->select('id')->where('faculty_id', $faculty->id))
                ->where('period', $period)
                ->lockForUpdate()
                ->get();

            if (SchedulePublication::query()->where('faculty_id', $faculty->id)->where('period', $period)->exists()) {
                throw ApiException::invalidState(__('The schedule of :period is published and final.', ['period' => Period::label($period)]));
            }
            $waiting = $this->unaccepted($faculty, $period);
            if ($waiting !== []) {
                throw ApiException::invalidState(__('Some programs are not accepted yet: :programs.', ['programs' => implode(', ', array_column($waiting, 'code'))]));
            }
            $accepted = $submissions->where('status', SubmissionStatus::ACCEPTED);
            if ($accepted->isEmpty()) {
                throw ApiException::invalidState(__('No program schedule has been accepted yet.'));
            }

            foreach ($accepted as $submission) {
                $submission->schedulingRun->update(['published_by' => $actor->id, 'published_at' => now()]);
                $submission->update(['status' => SubmissionStatus::PUBLISHED]);
            }
            SchedulePublication::query()->create([
                'faculty_id' => $faculty->id,
                'period' => $period,
                'published_by' => $actor->id,
                'published_at' => now(),
                'checksum' => $this->published->checksum($faculty->id, $period),
            ]);
        });

        activity('scheduling')->causedBy($actor)->event('schedule_publication.published')
            ->withProperties(['faculty_id' => $faculty->id, 'period' => $period])
            ->log(__('Published the schedule of :faculty for :period', ['faculty' => $faculty->code, 'period' => Period::label($period)]));

        return $this->show($faculty, $period);
    }

    public function unpublish(User $actor, Faculty $faculty, string $period, string $reason): array
    {
        $this->db->transaction(function () use ($faculty, $period) {
            $publication = SchedulePublication::query()->where('faculty_id', $faculty->id)->where('period', $period)->lockForUpdate()->first();
            if ($publication === null) {
                throw ApiException::invalidState(__('The schedule of :period is not published.', ['period' => Period::label($period)]));
            }

            $submissions = ProgramScheduleSubmission::query()
                ->whereIn('study_program_id', StudyProgram::query()->select('id')->where('faculty_id', $faculty->id))
                ->where('period', $period)
                ->where('status', SubmissionStatus::PUBLISHED)
                ->get();
            SchedulingRun::query()->whereIn('id', $submissions->pluck('scheduling_run_id'))->get()
                ->each(fn (SchedulingRun $run) => $run->update(['published_by' => null, 'published_at' => null]));
            $submissions->each(fn (ProgramScheduleSubmission $s) => $s->update(['status' => SubmissionStatus::ACCEPTED]));
            $publication->delete();
        });

        activity('scheduling')->causedBy($actor)->event('schedule_publication.withdrawn')
            ->withProperties(['faculty_id' => $faculty->id, 'period' => $period, 'reason' => $reason])
            ->log(__('Withdrew the published schedule of :faculty for :period', ['faculty' => $faculty->code, 'period' => Period::label($period)]));

        return $this->show($faculty, $period);
    }

    /**
     * Programs with a course to schedule in the period whose schedule is not accepted.
     *
     * @return list<array{id: int, code: string, name: string, status: string}>
     */
    private function unaccepted(Faculty $faculty, string $period): array
    {
        $programs = StudyProgram::query()->where('faculty_id', $faculty->id)
            ->whereIn('id', Course::query()->select('study_program_id')
                ->where('is_tpb', false)->where('is_active', true)->whereIn('semester', Period::semesters($period)))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $statuses = ProgramScheduleSubmission::query()->whereIn('study_program_id', $programs->pluck('id'))->where('period', $period)
            ->pluck('status', 'study_program_id');

        return $programs
            ->filter(fn (StudyProgram $p) => ! in_array($statuses[$p->id] ?? SubmissionStatus::DRAFT, [SubmissionStatus::ACCEPTED, SubmissionStatus::PUBLISHED], true))
            ->map(fn (StudyProgram $p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'status' => ($statuses[$p->id] ?? SubmissionStatus::DRAFT)->value])
            ->values()->all();
    }

    /**
     * @return list<int>
     */
    private function accepted(Faculty $faculty, string $period): array
    {
        return ProgramScheduleSubmission::query()
            ->whereIn('study_program_id', StudyProgram::query()->select('id')->where('faculty_id', $faculty->id))
            ->where('period', $period)
            ->where('status', SubmissionStatus::ACCEPTED)
            ->pluck('id')->all();
    }
}
