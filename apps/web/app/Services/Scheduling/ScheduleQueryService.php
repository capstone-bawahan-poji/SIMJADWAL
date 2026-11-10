<?php

namespace App\Services\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Permissions\Scheduling\SchedulingPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The official schedule of a period: genes of the active run of each faculty.
 * Exports read through visibleRuns() and details() too, so a file always matches the screen.
 *
 * Who sees what:
 *   - faculty: superadmin, TPB admin and lecturers read every faculty (a lecturer may teach in
 *     several); everyone else reads their own faculty only,
 *   - unpublished: an active run that is not published yet is seen only by those who may publish it.
 *
 * Default filters (own classes for lecturers) are a client concern.
 */
readonly class ScheduleQueryService
{
    public const FILTERS = ['faculty_id', 'study_program_id', 'semester', 'class_number', 'lecturer_id', 'room_id', 'course_id', 'day'];

    /** Boolean filters: genes without a room, genes in a room smaller than the class. */
    public const FLAGS = ['unassigned', 'over_capacity'];

    /*
     * Plus "classes": list of {course_id, class_number|null} a visitor picked (no KRS in the
     * system). It narrows on top of the other filters; class_number null = every class.
     */

    public function __construct(private ResolvesOrgScope $orgScope) {}

    /**
     * @param  array<string, mixed>  $filters  keys from FILTERS, FLAGS, scope and classes
     * @return array{period: string, runs: list<array<string, mixed>>, schedule_details: list<array<string, mixed>>}
     */
    public function getSchedule(User $actor, string $period, array $filters): array
    {
        $runs = $this->visibleRuns($actor, $period)
            ->with('faculty:id,code,name')
            ->when($filters['faculty_id'] ?? null, fn (Builder $q, int $id) => $q->where('faculty_id', $id))
            ->when(($filters['scope'] ?? null) === 'tpb', fn (Builder $q) => $q->whereNull('faculty_id'))
            ->when(($filters['scope'] ?? null) === 'faculty', fn (Builder $q) => $q->whereNotNull('faculty_id'))
            ->orderBy('faculty_id')
            ->get();

        return [
            'period' => $period,
            'runs' => $runs->map(fn (SchedulingRun $run) => [
                'id' => $run->id,
                'faculty' => $run->faculty ? ['id' => $run->faculty->id, 'code' => $run->faculty->code, 'name' => $run->faculty->name] : null,
                'scope' => $run->faculty_id === null ? 'tpb' : 'faculty',
                'published_at' => $run->published_at?->toIso8601String(),
            ])->values()->all(),
            'schedule_details' => ScheduleDetailTransformer::withTpb($this->details($runs->pluck('id')->all(), $filters)
                ->get()
                ->map(fn (ScheduleDetail $d) => [
                    'scheduling_run_id' => $d->scheduling_run_id,
                    ...ScheduleDetailTransformer::toArray($d),
                ])->values()->all(), $period),
        ];
    }

    /**
     * Active runs already published to everyone, for callers without an account (the public email).
     *
     * @return Builder<SchedulingRun>
     */
    public function publishedRuns(string $period): Builder
    {
        return SchedulingRun::query()->active()->where('period', $period)->whereNotNull('published_at');
    }

    /**
     * @return Builder<SchedulingRun>
     */
    public function visibleRuns(User $actor, string $period): Builder
    {
        $query = SchedulingRun::query()->active()->where('period', $period);

        if (! $this->orgScope->readsAllFacultySchedules($actor)) {
            $query->where(fn (Builder $q) => $this->orgScope->applyFacultyScope($q, $actor)->orWhereNull('faculty_id'));
        }

        if (! $actor->can(SchedulingPermissions::PUBLISH_RUN)) {
            return $query->whereNotNull('published_at');
        }

        // A publisher sees unpublished runs of the faculties it may publish: its own, or all (no faculty).
        $ownFacultyId = $this->orgScope->facultyId($actor);

        return $ownFacultyId === null ? $query : $query->where(fn (Builder $q) => $q
            ->whereNotNull('published_at')
            ->orWhere('faculty_id', $ownFacultyId));
    }

    /**
     * @param  list<int>  $runIds
     * @param  array<string, mixed>  $filters
     * @return Builder<ScheduleDetail>
     */
    public function details(array $runIds, array $filters): Builder
    {

        return ScheduleDetail::query()
            ->with(ScheduleDetailTransformer::relations())
            ->whereIn('scheduling_run_id', $runIds)
            ->when(isset($filters['study_program_id']) || isset($filters['semester']), fn (Builder $q) => $this->whereProgram($q, $filters['study_program_id'] ?? null, $filters['semester'] ?? null))
            ->when($filters['course_id'] ?? null, fn (Builder $q, int $id) => $q->where('course_id', $id))
            ->when($filters['class_number'] ?? null, fn (Builder $q, int $n) => $q->where('class_number', $n))
            ->when($filters['lecturer_id'] ?? null, fn (Builder $q, int $id) => $q->where('lecturer_id', $id))
            ->when($filters['room_id'] ?? null, fn (Builder $q, int $id) => $q->where('room_id', $id))
            ->when($filters['unassigned'] ?? false, fn (Builder $q) => $q->unassigned())
            ->when($filters['over_capacity'] ?? false, fn (Builder $q) => $q->overCapacity())
            ->when($filters['classes'] ?? [], fn (Builder $q, array $classes) => $q->where(function (Builder $any) use ($classes) {
                foreach ($classes as $class) {
                    $any->orWhere(fn (Builder $one) => $one
                        ->where('course_id', $class['course_id'])
                        ->when($class['class_number'], fn (Builder $c, int $n) => $c->where('class_number', $n)));
                }
            }))
            ->when($filters['day'] ?? null, fn (Builder $q, int $day) => $q->whereHas('timeSlot', fn (Builder $t) => $t->where('day', $day)))
            ->orderBy('time_slot_id')
            ->orderBy('id');
    }

    /**
     * Regular genes by their course's program and semester. TPB genes by the groups of their class:
     * a program attends a TPB class when its group lists the program (at that target semester).
     */
    private function whereProgram(Builder $query, ?int $studyProgramId, ?int $semester): Builder
    {
        return $query->where(fn (Builder $any) => $any
            ->whereHas('course', fn (Builder $c) => $c
                ->where('is_tpb', false)
                ->when($studyProgramId, fn (Builder $c) => $c->where('study_program_id', $studyProgramId))
                ->when($semester, fn (Builder $c) => $c->where('semester', $semester)))
            ->orWhereExists(fn (QueryBuilder $sub) => $sub
                ->selectRaw('1')
                ->from('course_lecturers')
                ->join('tpb_offerings', 'tpb_offerings.id', '=', 'course_lecturers.tpb_offering_id')
                ->join('scheduling_runs', 'scheduling_runs.period', '=', 'tpb_offerings.period')
                ->join('tpb_group_study_programs', 'tpb_group_study_programs.tpb_group_id', '=', 'course_lecturers.tpb_group_id')
                ->whereColumn('scheduling_runs.id', 'schedule_details.scheduling_run_id')
                ->whereColumn('course_lecturers.course_id', 'schedule_details.course_id')
                ->whereColumn('course_lecturers.class_number', 'schedule_details.class_number')
                ->when($studyProgramId, fn (QueryBuilder $s) => $s->where('tpb_group_study_programs.study_program_id', $studyProgramId))
                ->when($semester, fn (QueryBuilder $s) => $s->where('tpb_group_study_programs.target_semester', $semester))));
    }
}
