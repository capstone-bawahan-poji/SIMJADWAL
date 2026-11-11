<?php

namespace App\Services\Dashboard;

use App\Enums\Constraint\ConstraintStatus;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Services\Scheduling\ProgramScheduleSubmissionService;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Props of the faculty admin dashboard for the current period, computed in one page load.
 * Room clashes are not reported: upload, activation and override already reject them.
 */
readonly class FacultyDashboardService
{
    private const ISSUE_LIMIT = 5;

    public function __construct(
        private ProgramScheduleSubmissionService $submissions,
        private SchedulePublicationService $publications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(int $facultyId): array
    {
        $period = config('scheduling.current_period');
        $statuses = StudyProgram::query()->where('faculty_id', $facultyId)
            ->toBase()->selectRaw('constraint_status, count(*) as total')->groupBy('constraint_status')
            ->pluck('total', 'constraint_status');

        $faculty = Faculty::query()->findOrFail($facultyId);
        $programs = $this->submissions->forFaculty($faculty, $period);
        $publication = $this->publications->show($faculty, $period);
        $runIds = SchedulingRun::query()->active()->where('faculty_id', $facultyId)->whereNotNull('study_program_id')->where('period', $period)->pluck('id');

        return [
            'period' => $period,
            'constraints' => [
                'total' => (int) $statuses->sum(),
                ...collect(ConstraintStatus::cases())->mapWithKeys(fn (ConstraintStatus $s) => [$s->value => (int) ($statuses[$s->value] ?? 0)])->all(),
            ],
            'schedules' => [
                'programs' => $programs,
                'by_status' => collect($programs)->countBy('status')->all(),
                'pending_slot_requests' => collect($programs)->where('has_pending_slot_request', true)->count(),
                'published' => $publication['published'],
                'published_at' => $publication['published_at'],
                'can_publish' => $publication['can_publish'],
            ],
            'rooms' => $this->roomIssues($runIds->all()),
        ];
    }

    /**
     * @return array{unassigned_count: int, over_capacity_count: int, issues: list<array<string, mixed>>}
     */
    /**
     * @param  list<int>  $runIds  the active runs of the faculty's programs
     */
    private function roomIssues(array $runIds): array
    {
        $genes = fn () => ScheduleDetail::query()->whereIn('scheduling_run_id', $runIds);
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
