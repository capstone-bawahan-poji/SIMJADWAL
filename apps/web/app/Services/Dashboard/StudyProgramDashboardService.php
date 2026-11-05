<?php

namespace App\Services\Dashboard;

use App\Enums\Constraint\ConstraintCategory;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Services\Constraint\ConstraintSubmissionService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Props of the prodi admin dashboard for the current period. Schedule numbers appear only once
 * the faculty's active schedule is published, as for every role outside the faculty admin.
 */
readonly class StudyProgramDashboardService
{
    public function __construct(private ConstraintSubmissionService $submissions) {}

    /**
     * @return array<string, mixed>
     */
    public function get(StudyProgram $program): array
    {
        $period = config('scheduling.current_period');
        $summary = $this->submissions->summary($program);

        $ratios = [
            $summary->lecturersCount > 0 ? $summary->lecturersWithPreferencesCount / $summary->lecturersCount : 0,
            $summary->coursesCount > 0 ? ($summary->coursesCount - $summary->incompleteCoursesCount) / $summary->coursesCount : 0,
        ];

        $run = SchedulingRun::query()->active()->where('faculty_id', $program->faculty_id)->where('period', $period)->first();

        return [
            'period' => $period,
            'constraint' => [
                'status' => $summary->constraintStatus->value,
                'lecturers_count' => $summary->lecturersCount,
                'lecturers_with_preferences_count' => $summary->lecturersWithPreferencesCount,
                'courses_count' => $summary->coursesCount,
                'incomplete_courses_count' => $summary->incompleteCoursesCount,
                'completeness_percent' => (int) round(array_sum($ratios) / count($ratios) * 100),
            ],
            'schedule' => $this->schedule($run, $program),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedule(?SchedulingRun $run, StudyProgram $program): array
    {
        if ($run === null || $run->published_at === null) {
            return [
                'status' => $run === null ? 'none' : 'active',
                'classes_count' => null,
                'sks_total' => null,
                'hc_violations' => null,
                'unassigned_rooms' => null,
                'published_at' => null,
            ];
        }

        $genes = fn () => ScheduleDetail::query()
            ->where('scheduling_run_id', $run->id)
            ->whereHas('course', fn (Builder $q) => $q->where('study_program_id', $program->id));

        // One row per (course, class): a 4-SKS class has two meetings but counts once.
        $classes = $genes()->with('course:id,sks')->get(['course_id', 'class_number'])
            ->unique(fn (ScheduleDetail $d) => "{$d->course_id}:{$d->class_number}");

        return [
            'status' => 'published',
            'classes_count' => $classes->count(),
            'sks_total' => $classes->sum(fn (ScheduleDetail $d) => $d->course->sks),
            'hc_violations' => $genes()->whereHas('violationLogs.constraintType', fn (Builder $q) => $q->where('category', ConstraintCategory::HARD))->count(),
            'unassigned_rooms' => $genes()->unassigned()->count(),
            'published_at' => $run->published_at->toIso8601String(),
        ];
    }
}