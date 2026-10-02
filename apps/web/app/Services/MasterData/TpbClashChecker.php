<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TpbGroup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Clash rules for manually placed TPB groups. Callers write first inside a transaction,
 * then ask for errors, so the checks see the new state (and a failure rolls it back).
 *
 * Groups only clash within the same semester parity: odd semesters run in the GANJIL
 * period and even ones in GENAP, so they never share a real week.
 */
readonly class TpbClashChecker
{
    /**
     * A study program cannot attend two TPB groups of the same semester in one slot.
     *
     * @return list<string>
     */
    public function participantErrors(TpbGroup $group): array
    {
        if ($group->time_slot_id === null) {
            return [];
        }

        $programIds = $group->studyPrograms()->pluck('study_programs.id');

        return TpbGroup::query()
            ->with(['course', 'studyPrograms' => fn ($query) => $query->whereIn('study_programs.id', $programIds)])
            ->whereKeyNot($group->id)
            ->where('time_slot_id', $group->time_slot_id)
            ->whereRelation('course', 'semester', $group->course->semester)
            ->whereHas('studyPrograms', fn (Builder $query) => $query->whereIn('study_programs.id', $programIds))
            ->get()
            ->flatMap(fn (TpbGroup $other) => $other->studyPrograms->map(fn (StudyProgram $program) => __(
                'Study program :program already attends TPB group :group (:course) in this slot.',
                ['program' => $program->code, 'group' => $other->code, 'course' => $other->course->name],
            )))
            ->values()
            ->all();
    }

    /**
     * A lecturer or a room cannot hold two TPB classes in one slot.
     *
     * @return array<string, list<string>> field => messages
     */
    public function classErrors(CourseLecturer $class): array
    {
        $slotId = $class->tpbGroup?->time_slot_id;

        if ($slotId === null) {
            return [];
        }

        $sameSlot = fn () => CourseLecturer::query()
            ->with('course')
            ->whereKeyNot($class->id)
            ->whereRelation('tpbGroup', 'time_slot_id', $slotId)
            ->whereHas('course', fn (Builder $query) => $query->whereRaw('semester % 2 = ?', [$class->course->semester % 2]));

        $errors = [];

        $lecturerClash = $sameSlot()->where('lecturer_id', $class->lecturer_id)->first();
        if ($lecturerClash !== null) {
            $errors['lecturer_id'][] = __('The lecturer already teaches :course :class in this slot.', $this->describe($lecturerClash));
        }

        $roomClash = $class->room_id === null ? null : $sameSlot()->where('room_id', $class->room_id)->first();
        if ($roomClash !== null) {
            $errors['room_id'][] = __('The room is already used by :course :class in this slot.', $this->describe($roomClash));
        }

        return $errors;
    }

    /**
     * @return array{course: string, class: string}
     */
    private function describe(CourseLecturer $class): array
    {
        return ['course' => $class->course->name, 'class' => $class->class_label];
    }
}
