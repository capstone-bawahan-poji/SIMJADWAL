<?php

namespace App\Models\MasterData;

use App\Policies\MasterData\CourseLecturerPolicy;
use Database\Factories\MasterData\CourseLecturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One parallel class of a course, taught by exactly one lecturer.
 */
#[Fillable(['course_id', 'class_number', 'lecturer_id'])]
#[UsePolicy(CourseLecturerPolicy::class)]
class CourseLecturer extends Model
{
    /** @use HasFactory<CourseLecturerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'class_number' => 'integer',
        ];
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
}
