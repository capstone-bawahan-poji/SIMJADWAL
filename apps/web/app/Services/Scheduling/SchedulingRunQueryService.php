<?php

namespace App\Services\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Enums\Constraint\ConstraintCategory;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\ViolationLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

readonly class SchedulingRunQueryService
{
    public function __construct(private ResolvesOrgScope $orgScope) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function getRuns(User $actor, ?int $facultyId, ?string $period, ?string $batchId, int $perPage, ?bool $isActive = null): LengthAwarePaginator
    {
        return $this->summaryQuery()
            ->tap(fn (Builder $query) => $this->orgScope->applyFacultyScope($query, $actor))
            ->when($facultyId, fn (Builder $query) => $query->where('faculty_id', $facultyId))
            ->when($period, fn (Builder $query) => $query->where('period', $period))
            ->when($batchId, fn (Builder $query) => $query->where('batch_id', $batchId))
            ->when($isActive !== null, fn (Builder $query) => $query->where('is_active', $isActive))
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (SchedulingRun $run) => $this->summary($run));
    }

    /**
     * @return array<string, mixed>
     */
    public function getRun(SchedulingRun $run): array
    {
        $run = $this->summaryQuery()->findOrFail($run->id);

        return [
            ...$this->summary($run),
            'parameter_snapshot' => $run->parameter_snapshot,
            'fitness_history' => $run->fitness_history ?? [],
        ];
    }

    /**
     * Genes with everything a day × session matrix needs.
     *
     * @return list<array<string, mixed>>
     */
    public function getScheduleDetails(SchedulingRun $run, ?int $studyProgramId, ?int $day): array
    {
        return $run->scheduleDetails()
            ->with(ScheduleDetailTransformer::relations())
            ->withCount('violationLogs')
            ->when($studyProgramId, fn (Builder $query) => $query->whereHas('course', fn (Builder $q) => $q->where('study_program_id', $studyProgramId)))
            ->when($day, fn (Builder $query) => $query->whereHas('timeSlot', fn (Builder $q) => $q->where('day', $day)))
            ->orderBy('time_slot_id')
            ->orderBy('id')
            ->get()
            ->map(fn (ScheduleDetail $d) => [
                ...ScheduleDetailTransformer::toArray($d),
                'violations_count' => $d->violation_logs_count,
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getViolations(SchedulingRun $run, ?ConstraintCategory $category): array
    {
        return $run->violationLogs()
            ->with(['constraintType:id,code,category', 'scheduleDetail:id,course_id,class_number,meeting_number,time_slot_id'])
            ->when($category, fn (Builder $query) => $query->whereHas('constraintType', fn (Builder $q) => $q->where('category', $category)))
            ->orderBy('id')
            ->get()
            ->map(fn (ViolationLog $v) => [
                'id' => $v->id,
                'constraint_code' => $v->constraintType->code->value,
                'category' => $v->constraintType->category->value,
                'penalty' => (float) $v->penalty,
                'message' => $v->message,
                'schedule_detail_id' => $v->schedule_detail_id,
            ])->values()->all();
    }

    /** @return Builder<SchedulingRun> */
    private function summaryQuery(): Builder
    {
        return SchedulingRun::query()
            ->with(['triggeredBy:id,name', 'preset:id,name', 'faculty:id,code,name'])
            ->withCount([
                'violationLogs as hc_violations_count' => fn (Builder $q) => $q->whereHas('constraintType', fn (Builder $t) => $t->where('category', ConstraintCategory::HARD)),
                'violationLogs as sc_unmet_count' => fn (Builder $q) => $q->whereHas('constraintType', fn (Builder $t) => $t->where('category', ConstraintCategory::SOFT)),
                'scheduleDetails as unassigned_rooms_count' => fn (Builder $q) => $q->whereNull('room_id'),
                'scheduleDetails',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SchedulingRun $run): array
    {
        $snapshot = $run->parameter_snapshot ?? [];

        return [
            'id' => $run->id,
            'batch_id' => $run->batch_id,
            'faculty' => $run->faculty ? ['id' => $run->faculty->id, 'code' => $run->faculty->code, 'name' => $run->faculty->name] : null,
            'period' => $run->period,
            'preset' => $run->preset ? ['id' => $run->preset->id, 'name' => $run->preset->name] : null,
            'status' => $run->status->value,
            'selection_operator' => $snapshot['selection_operator'] ?? null,
            'crossover_rate' => $snapshot['crossover_rate'] ?? null,
            'mutation_rate' => $snapshot['mutation_rate'] ?? null,
            'population_size' => $snapshot['population_size'] ?? null,
            'parallel_class_limit' => $snapshot['parallel_class_limit'] ?? null,
            'seed' => $snapshot['seed'] ?? null,
            'best_fitness' => $run->best_fitness !== null ? (float) $run->best_fitness : null,
            'max_fitness' => $run->max_fitness !== null ? (float) $run->max_fitness : null,
            'fitness_percent' => $run->fitness_percent,
            'best_generation' => $run->best_generation,
            'final_generation' => $run->current_generation,
            'schedule_details_count' => $run->schedule_details_count,
            'hc_violations' => $run->hc_violations_count,
            'sc_unmet' => $run->sc_unmet_count,
            'unassigned_rooms' => $run->unassigned_rooms_count,
            'is_active' => $run->is_active,
            'triggered_by' => $run->triggeredBy ? ['id' => $run->triggeredBy->id, 'name' => $run->triggeredBy->name] : null,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'published_at' => $run->published_at?->toIso8601String(),
        ];
    }
}