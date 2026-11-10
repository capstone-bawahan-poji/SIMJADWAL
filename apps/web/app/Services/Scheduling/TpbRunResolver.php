<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\SchedulingRun;

/**
 * The TPB run in force for a period: active AND published. Faculties are scheduled only against
 * a published TPB run, and its slots lock for them at publish. Faculty runs record the TPB run
 * their dataset was built against (tpb_run_id); a different TPB run in force makes them stale
 * (a warning, not a refusal).
 */
class TpbRunResolver
{
    /** @var array<string, SchedulingRun|null> */
    private array $published = [];

    public function active(string $period): ?SchedulingRun
    {
        if (! array_key_exists($period, $this->published)) {
            $this->published[$period] = SchedulingRun::query()->active()->whereNull('faculty_id')
                ->whereNotNull('published_at')->where('period', $period)->first();
        }

        return $this->published[$period];
    }

    /**
     * The active TPB run even when it is not published yet.
     */
    public function activeAny(string $period): ?SchedulingRun
    {
        return SchedulingRun::query()->active()->whereNull('faculty_id')->where('period', $period)->first();
    }

    public function forget(): void
    {
        $this->published = [];
    }

    public function isStale(SchedulingRun $run): bool
    {
        return $run->faculty_id !== null && $run->tpb_run_id !== $this->active($run->period)?->id;
    }
}
