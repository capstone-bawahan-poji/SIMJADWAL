<?php

namespace App\Services\MasterData;

use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\Scheduling\ScheduleDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Refuses a delete with 409 RESOURCE_IN_USE while other data still points at the row.
 * Soft-deleted master data only counts live references: history in inactive runs
 * keeps its foreign keys valid because the row is never removed.
 */
readonly class ReferenceGuard
{
    /**
     * @param  array<string, int>  $counts  ReferenceType value => number of referencing rows
     */
    public function ensureUnused(array $counts): void
    {
        $used = array_filter($counts, fn (int $count) => $count > 0);

        if ($used === []) {
            return;
        }

        $summary = collect($used)
            ->map(fn (int $count, string $type) => ReferenceType::from($type)->label()." ({$count})")
            ->implode(', ');

        throw ApiException::resourceInUse($used, __('The data is still used by :references.', ['references' => $summary]));
    }

    /**
     * Schedule rows of active (published) runs only.
     *
     * @param  HasMany<ScheduleDetail, *>  $scheduleDetails
     */
    public function activeScheduleCount(HasMany $scheduleDetails): int
    {
        return $scheduleDetails->whereHas('schedulingRun', fn (Builder $query) => $query->active())->count();
    }
}
