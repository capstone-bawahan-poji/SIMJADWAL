<?php

namespace App\Services\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use App\Permissions\Scheduling\SchedulingPermissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * The official schedule of a period: genes of the active run of each faculty.
 * Exports read through visibleRuns() and details() too, so a file always matches the screen.
 *
 * Who sees what:
 *   - faculty: superadmin, TPB admin and lecturers read every faculty (a lecturer may teach in
 *     several); everyone else reads their own faculty only,
 *   - unpublished: an active run that is not published yet is seen only by those who may publish it.
 *
 * Default filters (own program for students, own classes for lecturers) are a client concern.
 */
readonly class ScheduleQueryService
{
    public const FILTERS = ['faculty_id', 'study_program_id', 'semester', 'class_number', 'lecturer_id', 'room_id', 'course_id', 'day'];

    /** Boolean filters: genes without a room, genes in a room smaller than the class. */
    public const FLAGS = ['unassigned', 'over_capacity'];

    /*
     * Plus "classes": list of {course_id, class_number|null} a student picked (no KRS in the
     * system). It narrows on top of the other filters; class_number null = every class.
     */

    public function __construct(private ResolvesOrgScope $orgScope) {}

    /**
     * @param  array<string, mixed>  $filters  keys from FILTERS, FLAGS and classes
     * @return array{period: string, runs: list<array<string, mixed>>, schedule_details: list<array<string, mixed>>}
     */
    public function getSchedule(User $actor, string $period, array $filters): array
    {
        $runs = $this->visibleRuns($actor, $period)
            ->with('faculty:id,code,name')
            ->when($filters['faculty_id'] ?? null, fn (Builder $q, int $id) => $q->where('faculty_id', $id))
            ->orderBy('faculty_id')
            ->get();

        return [
            'period' => $period,
            'runs' => $runs->map(fn (SchedulingRun $run) => [
                'id' => $run->id,
                'faculty' => ['id' => $run->faculty->id, 'code' => $run->faculty->code, 'name' => $run->faculty->name],
                'published_at' => $run->published_at?->toIso8601String(),
            ])->values()->all(),
            'schedule_details' => $this->details($runs->pluck('id')->all(), $filters)
                ->get()
                ->map(fn (ScheduleDetail $d) => [
                    'scheduling_run_id' => $d->scheduling_run_id,
                    ...ScheduleDetailTransformer::toArray($d),
                ])->values()->all(),
        ];
    }

    /**
     * @return Builder<SchedulingRun>
     */
    public function visibleRuns(User $actor, string $period): Builder
    {
        $query = SchedulingRun::query()->active()->where('period', $period);

        if (! $this->orgScope->readsAllFacultySchedules($actor)) {
            $this->orgScope->applyFacultyScope($query, $actor);
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
        $course = fn (string $column) => fn (Builder $q, int $value) => $q->whereHas('course', fn (Builder $c) => $c->where($column, $value));

        return ScheduleDetail::query()
            ->with(ScheduleDetailTransformer::relations())
            ->whereIn('scheduling_run_id', $runIds)
            ->when($filters['study_program_id'] ?? null, $course('study_program_id'))
            ->when($filters['semester'] ?? null, $course('semester'))
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
}
