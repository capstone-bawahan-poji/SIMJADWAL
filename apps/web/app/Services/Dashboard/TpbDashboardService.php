<?php

namespace App\Services\Dashboard;

use App\Enums\MasterData\TpbCohort;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Services\Scheduling\SchedulingRunQueryService;
use App\Services\Scheduling\TpbRunResolver;

/**
 * Props of the TPB admin dashboard for the current period: schedule status first, then what
 * still blocks a GA run (classes without group or lecturer), then load and room figures.
 */
readonly class TpbDashboardService
{
    public function __construct(
        private SchedulingRunQueryService $runQuery,
        private TpbRunResolver $tpbRuns,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $period = config('scheduling.current_period');
        $offerings = TpbOffering::query()->where('period', $period)
            ->withCount(['groups', 'courseLecturers', 'assignedClasses'])
            ->get();
        $classes = (int) $offerings->sum('parallel_class_count');
        $grouped = (int) $offerings->sum('course_lecturers_count');
        $assigned = (int) $offerings->sum('assigned_classes_count');
        $active = $this->tpbRuns->activeAny($period);
        $published = $this->tpbRuns->active($period);

        return [
            'period' => $period,
            'schedule' => $this->schedule($period, $active),
            'offerings' => [
                'count' => $offerings->count(),
                'groups' => (int) $offerings->sum('groups_count'),
                'classes' => $classes,
                'seats' => (int) $offerings->sum(fn (TpbOffering $o) => $o->parallel_class_count * $o->class_capacity),
            ],
            'classes' => [
                'total' => $classes,
                'ungrouped' => $classes - $grouped,
                'without_lecturer' => $grouped - $assigned,
            ],
            'stale_faculties' => $this->staleFaculties($period, $published),
            'lecturers' => $this->lecturerLoad($period),
            'rooms' => $this->roomPeak($active),
            'retake_programs' => TpbGroup::query()
                ->where('cohort', TpbCohort::MENGULANG)
                ->whereRelation('offering', 'period', $period)
                ->join('tpb_group_study_programs', 'tpb_group_study_programs.tpb_group_id', '=', 'tpb_groups.id')
                ->distinct()
                ->count('tpb_group_study_programs.study_program_id'),
            'cohorts' => collect(TpbCohort::cases())->mapWithKeys(fn (TpbCohort $cohort) => [$cohort->value => 0])
                ->merge(CourseLecturer::query()
                    ->join('tpb_groups', 'tpb_groups.id', '=', 'course_lecturers.tpb_group_id')
                    ->whereRelation('tpbOffering', 'period', $period)
                    ->toBase()
                    ->selectRaw('tpb_groups.cohort, count(*) as total')
                    ->groupBy('tpb_groups.cohort')
                    ->pluck('total', 'cohort')
                    ->map(fn ($total) => (int) $total))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedule(string $period, ?SchedulingRun $active): array
    {
        $runs = SchedulingRun::query()->whereNull('faculty_id')->where('period', $period)->count();
        $status = match (true) {
            $active?->published_at !== null => 'published',
            $active !== null => 'active',
            $runs > 0 => 'draft',
            default => 'none',
        };

        return [
            'status' => $status,
            'runs_count' => $runs,
            'active_run' => $active ? collect($this->runQuery->getRun($active))->only([
                'id', 'published_at', 'finished_at', 'fitness_percent', 'hc_violations', 'sc_unmet', 'unassigned_rooms', 'schedule_details_count',
            ])->all() : null,
        ];
    }

    /**
     * Faculties with an active program run built against another TPB run.
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    private function staleFaculties(string $period, ?SchedulingRun $active): array
    {
        return SchedulingRun::query()->active()
            ->with('faculty:id,code,name')
            ->whereNotNull('faculty_id')
            ->where('period', $period)
            ->where(fn ($query) => $query->whereNull('tpb_run_id')->when($active, fn ($q) => $q->orWhere('tpb_run_id', '!=', $active->id)))
            ->get()
            ->map(fn (SchedulingRun $run) => ['id' => $run->faculty->id, 'code' => $run->faculty->code, 'name' => $run->faculty->name])
            ->unique('id')->sortBy('code')->values()->all();
    }

    /**
     * @return array{count: int, limit: int, over_limit: int, top: array{name: string, sks: int}|null}
     */
    private function lecturerLoad(string $period): array
    {
        $limit = (int) config('scheduling.tpb_max_lecturer_sks');
        $loads = CourseLecturer::query()
            ->join('courses', 'courses.id', '=', 'course_lecturers.course_id')
            ->join('lecturers', 'lecturers.id', '=', 'course_lecturers.lecturer_id')
            ->whereRelation('tpbOffering', 'period', $period)
            ->toBase()
            ->selectRaw('lecturers.id, lecturers.name, sum(courses.sks) as sks')
            ->groupBy('lecturers.id', 'lecturers.name')
            ->orderByDesc('sks')
            ->orderBy('lecturers.name')
            ->get();
        $top = $loads->first();

        return [
            'count' => $loads->count(),
            'limit' => $limit,
            'over_limit' => $loads->filter(fn ($row) => (int) $row->sks > $limit)->count(),
            'top' => $top ? ['name' => $top->name, 'sks' => (int) $top->sks] : null,
        ];
    }

    /**
     * Busiest slot of the active TPB run against the shared rooms.
     *
     * @return array{shared: int, peak: array{day: int, session: int, used: int}|null}
     */
    private function roomPeak(?SchedulingRun $active): array
    {
        $shared = Room::query()->whereNull('faculty_id')->count();
        if ($active === null) {
            return ['shared' => $shared, 'peak' => null];
        }

        $row = ScheduleDetail::query()->where('scheduling_run_id', $active->id)
            ->whereNotNull('room_id')
            ->toBase()
            ->selectRaw('time_slot_id, count(distinct room_id) as used')
            ->groupBy('time_slot_id')
            ->orderByDesc('used')
            ->orderBy('time_slot_id')
            ->first();
        $slot = $row ? TimeSlot::query()->find($row->time_slot_id) : null;

        return ['shared' => $shared, 'peak' => $slot ? ['day' => $slot->day->value, 'session' => $slot->session, 'used' => (int) $row->used] : null];
    }
}
