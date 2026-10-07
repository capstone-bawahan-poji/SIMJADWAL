<?php

namespace App\Models\Constraint;

use App\Enums\Constraint\ConstraintCategory;
use App\Enums\Constraint\ConstraintCode;
use App\Models\Scheduling\ViolationLog;
use App\Policies\Constraint\ConstraintTypePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rows come from ConstraintTypeSeeder only; weight is the only editable column.
 */
#[Fillable(['code', 'category', 'description', 'weight'])]
#[UsePolicy(ConstraintTypePolicy::class)]
class ConstraintType extends Model
{
    protected function casts(): array
    {
        return [
            'code' => ConstraintCode::class,
            'category' => ConstraintCategory::class,
            'weight' => 'decimal:2',
        ];
    }

    /** @return HasMany<LecturerPreference, $this> */
    public function lecturerPreferences(): HasMany
    {
        return $this->hasMany(LecturerPreference::class);
    }

    /** @return HasMany<ViolationLog, $this> */
    public function violationLogs(): HasMany
    {
        return $this->hasMany(ViolationLog::class);
    }
}
