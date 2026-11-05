<?php

namespace App\Services\Dashboard;

use App\Enums\Constraint\ConstraintStatus;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Services\Scheduling\SchedulingRunQueryService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Props of the faculty admin dashboard for the current period, computed in one page load.
 * Room clashes are not reported: upload, activation and override already reject them.
 */
readonly class FacultyDashboardService
{
    private const ISSUE_LIMIT = 5;

    public function __construct(private SchedulingRunQueryService $runQuery) {}

    /**
     * @return array<string, mixed>
     */
    public function get(int $facultyId): array
    {
        $period = config('scheduling.current_period');
        $statuses = StudyProgram::query()->where('faculty_id', $facultyId)
            ->toBase()->selectRaw('constraint_status, count(*) as total')->groupBy('constraint_status')
            ->pluck('total', 'constraint_status');

        $run = SchedulingRun::query()->active()->where('faculty_id', $facultyId)->where('period', $period)->first();

        return [
            'period' => $period,
            'constraints' => [
                'total' => (int) $statuses->sum(),
                ...collect(ConstraintStatus::cases())->mapWithKeys(fn (ConstraintStatus $s) => [$s->value => (int) ($statuses[$s->value] ?? 0)])->all(),
            ],
            'active_run' => $run ? $this->activeRun($run) : null,
            'rooms' => $run ? $this->roomIssues($run) : ['unassigned_count' => 0, 'over_capacity_count' => 0, 'issues' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeRun(SchedulingRun $run): array
    {
        $summary = $this->runQuery->getRun($run);

        return collect($summary)->only([
            'id', 'published_at', 'best_fitness', 'fitness_percent', 'final_generation',
            'schedule_details_count', 'hc_violations', 'sc_unmet',
        ])->all();
    }

    /**
     * @return array{unassigned_count: int, over_capacity_count: int, issues: list<array<string, mixed>>}
     */
    private function roomIssues(SchedulingRun $run): array
    {
        $genes = fn () => ScheduleDetail::query()->where('scheduling_run_id', $run->id);
        $issues = fn (Builder $query, string $type) => $query
            ->with(['course:id,name', 'timeSlot', 'room:id,code'])
            ->orderBy('time_slot_id')->orderBy('id')
            ->limit(self::ISSUE_LIMIT)
            ->get()
            ->map(fn (ScheduleDetail $d) => [
                'schedule_detail_id' => $d->id,
                'type' => $type,
                'course' => $d->course->name,
                'class_label' => CourseLecturer::labelFor($d->class_number),
                'day' => $d->timeSlot->day->value,
                'session' => $d->timeSlot->session,
                'room' => $d->room?->code,
            ]);

        return [
            'unassigned_count' => $genes()->unassigned()->count(),
            'over_capacity_count' => $genes()->overCapacity()->count(),
            'issues' => $issues($genes()->unassigned(), 'unassigned')
                ->concat($issues($genes()->overCapacity(), 'over_capacity'))
                ->take(self::ISSUE_LIMIT)
                ->values()->all(),
        ];
    }
}