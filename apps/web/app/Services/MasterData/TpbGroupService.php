<?php

namespace App\Services\MasterData;

use App\Data\MasterData\TpbGroupData;
use App\Data\MasterData\TpbGroupFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * TPB groups are placed by hand in the MVP. Every write re-checks the clash rules
 * (TpbClashChecker) against the saved state and rolls back on failure.
 */
readonly class TpbGroupService
{
    public function __construct(
        private TpbGroup $tpbGroup,
        private TpbClashChecker $clashChecker,
        private ReferenceGuard $referenceGuard,
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<int, TpbGroupData>
     */
    public function getGroups(?int $courseId, ?int $studyProgramId, ?int $timeSlotId, int $perPage): LengthAwarePaginator
    {
        return TpbGroupData::prepareQuery($this->tpbGroup->newQuery())
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->when($studyProgramId, fn (Builder $query) => $query->whereRelation('studyPrograms', 'study_programs.id', $studyProgramId))
            ->when($timeSlotId, fn (Builder $query) => $query->where('time_slot_id', $timeSlotId))
            ->orderBy('course_id')
            ->orderBy('code')
            ->paginate($perPage)
            ->through(fn (TpbGroup $group) => TpbGroupData::from($group));
    }

    /**
     * Placed groups a program attends: the slots it cannot use for its own courses of that semester.
     *
     * @return Collection<int, TpbGroupData>
     */
    public function getPlacedGroupsFor(StudyProgram $studyProgram): Collection
    {
        return TpbGroupData::prepareQuery($this->tpbGroup->newQuery())
            ->whereRelation('studyPrograms', 'study_programs.id', $studyProgram->id)
            ->whereNotNull('time_slot_id')
            ->get()
            ->sortBy(fn (TpbGroup $group) => [$group->course->semester, $group->timeSlot->day->value, $group->timeSlot->session])
            ->values()
            ->map(fn (TpbGroup $group) => TpbGroupData::from($group));
    }

    public function getGroup(TpbGroup $group): TpbGroupData
    {
        return TpbGroupData::from(TpbGroupData::loadRelations($group));
    }

    public function createGroup(TpbGroupFormData $data): TpbGroupData
    {
        $group = $this->db->transaction(function () use ($data) {
            $group = $this->tpbGroup->newQuery()->create([
                'course_id' => $data->courseId,
                'code' => $data->code,
                'time_slot_id' => $data->timeSlotId,
            ]);
            $group->studyPrograms()->sync($data->studyProgramIds);
            $this->ensureNoClash($group);

            return $group;
        });

        return $this->getGroup($group);
    }

    /**
     * The course never changes: UpdateTpbGroupRequest prohibits course_id.
     */
    public function updateGroup(TpbGroup $group, TpbGroupFormData $data): TpbGroupData
    {
        $this->db->transaction(function () use ($group, $data) {
            $group->update(['code' => $data->code, 'time_slot_id' => $data->timeSlotId]);
            $group->studyPrograms()->sync($data->studyProgramIds);
            $this->ensureNoClash($group->fresh());
        });

        return $this->getGroup($group->fresh());
    }

    public function deleteGroup(TpbGroup $group): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::TEACHING_ASSIGNMENTS->value => $group->courseLecturers()->count(),
        ]);

        $group->delete();
    }

    /**
     * Checks the programs of the group and, after a slot change, every class in it.
     */
    private function ensureNoClash(TpbGroup $group): void
    {
        $errors = [];

        foreach ($this->clashChecker->participantErrors($group) as $message) {
            $errors['study_program_ids'][] = $message;
        }

        $group->courseLecturers()->with(['course', 'tpbGroup'])->get()->each(function (CourseLecturer $class) use (&$errors) {
            foreach ($this->clashChecker->classErrors($class) as $messages) {
                foreach ($messages as $message) {
                    $errors['time_slot_id'][] = __('Class :class: :message', ['class' => $class->class_label, 'message' => $message]);
                }
            }
        });

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
    }
}
