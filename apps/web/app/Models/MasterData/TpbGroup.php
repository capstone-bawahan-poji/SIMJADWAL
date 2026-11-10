<?php

namespace App\Models\MasterData;

use App\Enums\MasterData\TpbCohort;
use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Policies\MasterData\TpbGroupPolicy;
use Database\Factories\MasterData\TpbGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Classes of one offering that always share a slot. The slot comes from the active TPB run.
 */
#[Fillable(['tpb_offering_id', 'code', 'cohort'])]
#[UsePolicy(TpbGroupPolicy::class)]
class TpbGroup extends Model
{
    /** @use HasFactory<TpbGroupFactory> */
    use BroadcastsChanges, HasFactory;

    protected function casts(): array
    {
        return [
            'cohort' => TpbCohort::class,
        ];
    }

    /**
     * Participating programs with the semester they take the course in. Needs studyPrograms loaded.
     *
     * @return Attribute<list<array{study_program_id: int, code: string, name: string, target_semester: int|null}>, never>
     */
    protected function participants(): Attribute
    {
        return Attribute::get(fn () => $this->studyPrograms
            ->sortBy('code')
            ->map(fn (StudyProgram $program) => [
                'study_program_id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'target_semester' => $program->pivot->target_semester === null ? null : (int) $program->pivot->target_semester,
            ])->values()->all());
    }

    /**
     * Class numbers of the group, ascending. Needs courseLecturers loaded.
     *
     * @return Attribute<list<int>, never>
     */
    protected function classNumbers(): Attribute
    {
        return Attribute::get(fn () => $this->courseLecturers->pluck('class_number')->sort()->values()->all());
    }

    /** @return BelongsTo<TpbOffering, $this> */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(TpbOffering::class, 'tpb_offering_id');
    }

    /** @return BelongsToMany<StudyProgram, $this> */
    public function studyPrograms(): BelongsToMany
    {
        return $this->belongsToMany(StudyProgram::class, 'tpb_group_study_programs')->withPivot('target_semester')->withTimestamps();
    }

    /** @return HasMany<CourseLecturer, $this> */
    public function courseLecturers(): HasMany
    {
        return $this->hasMany(CourseLecturer::class);
    }
}
