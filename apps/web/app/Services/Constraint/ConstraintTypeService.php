<?php

namespace App\Services\Constraint;

use App\Data\Constraint\ConstraintTypeData;
use App\Models\Constraint\ConstraintType;
use Illuminate\Support\Collection;

/**
 * Weights are global: one value per constraint code for every faculty. Only soft
 * constraints can change (ConstraintTypePolicy).
 */
readonly class ConstraintTypeService
{
    public function __construct(
        private ConstraintType $constraintType,
        private ConstraintActivityLogService $activityLog,
    ) {}

    /**
     * Not paginated: 7 rows.
     *
     * @return Collection<int, ConstraintTypeData>
     */
    public function getConstraintTypes(): Collection
    {
        return $this->constraintType->newQuery()
            ->orderBy('category')
            ->orderBy('code')
            ->get()
            ->map(fn (ConstraintType $constraintType) => ConstraintTypeData::from($constraintType));
    }

    public function updateWeight(ConstraintType $constraintType, float $weight): ConstraintTypeData
    {
        $old = (string) $constraintType->weight;
        $constraintType->update(['weight' => $weight]);
        $this->activityLog->constraintWeightChanged($constraintType->fresh(), $old);

        return ConstraintTypeData::from($constraintType->fresh());
    }
}
