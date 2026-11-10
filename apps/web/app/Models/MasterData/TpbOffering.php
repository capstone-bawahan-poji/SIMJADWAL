<?php

namespace App\Models\MasterData;

use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Policies\MasterData\TpbOfferingPolicy;
use Database\Factories\MasterData\TpbOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A TPB course opened in one period (YYYY/YYYY-GANJIL|GENAP), with its class count and capacity.
 */
#[Fillable(['course_id', 'period', 'parallel_class_count', 'class_capacity'])]
#[UsePolicy(TpbOfferingPolicy::class)]
class TpbOffering extends Model
{
    /** @use HasFactory<TpbOfferingFactory> */
    use BroadcastsChanges, HasFactory;

    protected function casts(): array
    {
        return [
            'parallel_class_count' => 'integer',
            'class_capacity' => 'integer',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /** @return HasMany<TpbGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TpbGroup::class);
    }

    /** @return HasMany<CourseLecturer, $this> classes, created when they join a group */
    public function courseLecturers(): HasMany
    {
        return $this->hasMany(CourseLecturer::class);
    }

    /** @return HasMany<CourseLecturer, $this> classes that already have a lecturer */
    public function assignedClasses(): HasMany
    {
        return $this->hasMany(CourseLecturer::class)->whereNotNull('lecturer_id');
    }
}
