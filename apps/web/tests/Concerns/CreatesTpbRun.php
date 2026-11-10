<?php

namespace Tests\Concerns;

use App\Enums\Scheduling\RunStatus;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Faculties are scheduled after TPB, so faculty scheduling tests need an active TPB run.
 */
trait CreatesTpbRun
{
    /**
     * @param  list<array<string, int|null>>  $details  schedule_details rows without scheduling_run_id
     */
    protected function activeTpbRun(string $period, array $details = [], bool $published = true): SchedulingRun
    {
        $user = User::factory()->tpbAdmin()->create();
        $run = SchedulingRun::query()->create([
            'client_run_id' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'faculty_id' => null,
            'period' => $period,
            'parameter_snapshot' => ['parallel_class_limit' => 5, 'weights' => []],
            'status' => RunStatus::DONE,
            'best_fitness' => 500,
            'max_fitness' => 500,
            'is_active' => true,
            'triggered_by' => $user->id,
            'published_by' => $published ? $user->id : null,
            'published_at' => $published ? now() : null,
        ]);
        foreach ($details as $detail) {
            ScheduleDetail::query()->create(['scheduling_run_id' => $run->id, 'meeting_number' => 1, ...$detail]);
        }

        return $run;
    }
}
