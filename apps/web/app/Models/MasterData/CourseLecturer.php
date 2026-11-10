<?php

namespace App\Models\MasterData;

use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Policies\MasterData\CourseLecturerPolicy;
use Database\Factories\MasterData\CourseLecturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One parallel class of a course, taught by exactly one lecturer.
 * tpb_offering_id and tpb_group_id are set for TPB classes only: class numbers restart in every offering.
 */
#[Fillable(['course_id', 'class_number', 'lecturer_id', 'tpb_offering_id', 'tpb_group_id'])]
#[UsePolicy(CourseLecturerPolicy::class)]
class CourseLecturer extends Model
{
    /** @use HasFactory<CourseLecturerFactory> */
    use BroadcastsChanges, HasFactory;

    protected function casts(): array
    {
        return [
            'class_number' => 'integer',
        ];
    }

    /**
     * Class name shown to users: 1 = A, 2 = B, ..., 26 = Z, 27 = AA.
     */
    public static function labelFor(int $classNumber): string
    {
        $label = '';

        for ($n = $classNumber; $n > 0; $n = intdiv($n - 1, 26)) {
            $label = chr(65 + ($n - 1) % 26).$label;
        }

        return $label;
    }

    /** @return Attribute<string, never> */
    protected function classLabel(): Attribute
    {
        return Attribute::get(fn () => self::labelFor($this->class_number));
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<TpbOffering, $this> */
    public function tpbOffering(): BelongsTo
    {
        return $this->belongsTo(TpbOffering::class);
    }

    /** @return BelongsTo<TpbGroup, $this> */
    public function tpbGroup(): BelongsTo
    {
        return $this->belongsTo(TpbGroup::class);
    }
}
