<?php

namespace App\Services\MasterData;

use App\Data\MasterData\TpbGroupData;
use App\Data\MasterData\TpbGroupFormData;
use App\Enums\MasterData\TpbCohort;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbGroup;
use App\Models\MasterData\TpbOffering;
use App\Services\Scheduling\Period;
use App\Services\Shared\ChangeRecorder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Groups split the classes of one offering. A group owns its classes: adding a class number
 * creates the class (without a lecturer yet), removing it deletes the class and its lecturer.
 */
readonly class TpbGroupService
{
    public function __construct(
        private TpbGroup $tpbGroup,
        private DatabaseManager $db,
        private ChangeRecorder $changes,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TpbGroupData>
     */
    public function getGroups(?int $offeringId, ?string $period, ?int $studyProgramId, ?TpbCohort $cohort, int $perPage): LengthAwarePaginator
    {
        return TpbGroupData::prepareQuery($this->tpbGroup->newQuery())
            ->when($offeringId, fn (Builder $query) => $query->where('tpb_offering_id', $offeringId))
            ->when($period, fn (Builder $query) => $query->whereRelation('offering', 'period', $period))
            ->when($studyProgramId, fn (Builder $query) => $query->whereRelation('studyPrograms', 'study_programs.id', $studyProgramId))
            ->when($cohort, fn (Builder $query) => $query->where('cohort', $cohort))
            ->orderBy('tpb_offering_id')
            ->orderBy('code')
            ->paginate($perPage)
            ->through(fn (TpbGroup $group) => TpbGroupData::from($group));
    }

    public function getGroup(TpbGroup $group): TpbGroupData
    {
        return TpbGroupData::from(TpbGroupData::loadRelations($group->unsetRelations()));
    }

    public function createGroup(TpbGroupFormData $data): TpbGroupData
    {
        $offering = TpbOffering::query()->findOrFail($data->tpbOfferingId);
        $this->ensureValid($offering, $data, null);

        $group = $this->db->transaction(function () use ($offering, $data) {
            $group = $this->tpbGroup->newQuery()->create([
                'tpb_offering_id' => $offering->id,
                'code' => $data->code,
                'cohort' => $data->cohort,
            ]);
            $this->syncMembers($group, $offering, $data);

            return $group;
        });

        return $this->getGroup($group);
    }

    /**
     * The offering never changes: UpdateTpbGroupRequest prohibits tpb_offering_id.
     */
    public function updateGroup(TpbGroup $group, TpbGroupFormData $data): TpbGroupData
    {
        $this->ensureValid($group->offering, $data, $group);

        $this->db->transaction(function () use ($group, $data) {
            $group->update(['code' => $data->code, 'cohort' => $data->cohort]);
            $this->syncMembers($group, $group->offering, $data);
            // sync() writes the pivot only, so a change of programs alone fires no model event.
            $this->changes->record($group->getTable(), $group->id);
        });

        return $this->getGroup($group->fresh());
    }

    /**
     * Deletes the group together with its classes and their lecturers.
     */
    public function deleteGroup(TpbGroup $group): void
    {
        $this->db->transaction(function () use ($group) {
            $group->courseLecturers()->get()->each(fn (CourseLecturer $class) => $class->delete());
            $group->delete();
        });
    }

    private function syncMembers(TpbGroup $group, TpbOffering $offering, TpbGroupFormData $data): void
    {
        $group->studyPrograms()->sync(collect($data->participants)
            ->mapWithKeys(fn (?int $semester, int $programId) => [$programId => ['target_semester' => $semester]])
            ->all());

        $group->courseLecturers()->whereNotIn('class_number', $data->classNumbers)->get()
            ->each(fn (CourseLecturer $class) => $class->delete());
        $existing = $group->courseLecturers()->pluck('class_number')->all();
        foreach (array_diff($data->classNumbers, $existing) as $classNumber) {
            $group->courseLecturers()->create([
                'course_id' => $offering->course_id,
                'class_number' => $classNumber,
                'tpb_offering_id' => $offering->id,
                'lecturer_id' => null,
            ]);
        }
    }

    private function ensureValid(TpbOffering $offering, TpbGroupFormData $data, ?TpbGroup $current): void
    {
        $errors = [];

        $tooHigh = array_filter($data->classNumbers, fn (int $n) => $n > $offering->parallel_class_count);
        if ($tooHigh !== []) {
            $errors['class_numbers'][] = __('The offering only has :count classes.', ['count' => $offering->parallel_class_count]);
        }

        $taken = CourseLecturer::query()
            ->with('tpbGroup:id,code')
            ->where('tpb_offering_id', $offering->id)
            ->whereIn('class_number', $data->classNumbers)
            ->when($current, fn (Builder $query) => $query->where('tpb_group_id', '!=', $current->id))
            ->orderBy('class_number')
            ->get();
        foreach ($taken as $class) {
            $errors['class_numbers'][] = __('Class :class already belongs to group :group.', ['class' => $class->class_label, 'group' => $class->tpbGroup->code]);
        }

        if ($data->cohort->hasPrograms() && $data->participants === []) {
            $errors['study_programs'][] = __('A group needs at least one study program.');
        }
        if (! $data->cohort->hasPrograms() && $data->participants !== []) {
            $errors['study_programs'][] = __('An elective group is open to every study program and lists none.');
        }

        $odd = Period::isOdd($offering->period);
        foreach ($data->participants as $semester) {
            if ($data->cohort->isRegular() && $semester === null) {
                $errors['study_programs'][] = __('Every study program needs a target semester.');
                break;
            }
            if (! $data->cohort->isRegular() && $semester !== null) {
                $errors['study_programs'][] = __('Only regular groups have a target semester.');
                break;
            }
            if ($semester !== null && ($semester % 2 === 1) !== $odd) {
                $errors['study_programs'][] = __('The target semester must match the period (:period).', ['period' => Period::label($offering->period)]);
                break;
            }
        }

        // Per offering, a program attends one regular group per target semester and one retake group.
        $twice = StudyProgram::query()
            ->whereKey(array_keys($data->participants))
            ->whereHas('tpbGroups', fn (Builder $query) => $query
                ->where('tpb_offering_id', $offering->id)
                ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id))
                ->when(
                    $data->cohort->isRegular(),
                    fn (Builder $query) => $query->whereIn('cohort', TpbCohort::regular())->where(fn (Builder $query) => collect($data->participants)
                        ->each(fn (?int $semester, int $programId) => $query->orWhere(fn (Builder $one) => $one
                            ->where('tpb_group_study_programs.study_program_id', $programId)
                            ->where('tpb_group_study_programs.target_semester', $semester)))),
                    fn (Builder $query) => $query->where('cohort', $data->cohort),
                ))
            ->orderBy('code')
            ->pluck('code');
        if ($twice->isNotEmpty()) {
            $errors['study_programs'][] = __('Study programs :programs already attend another group of this course for the same students.', ['programs' => $twice->implode(', ')]);
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
    }
}
