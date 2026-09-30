<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\TpbGroup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Slots taken by placed TPB groups. TPB is scheduled before any faculty, so the faculty
 * GA (Module 4) must remove these slots from the domain of the matching genes:
 *
 *   - a program's courses of semester s skip the slots of TPB groups it attends in semester s,
 *   - a lecturer skips the slots of its TPB classes,
 *   - a room skips the slots of the TPB classes placed in it.
 *
 * Lecturer and room blocks only apply within the same semester parity (GANJIL or GENAP).
 */
readonly class TpbBlockService
{
    /**
     * @return array<int, array<int, list<int>>> study_program_id => semester => time_slot_ids
     */
    public function blockedSlotsByStudyProgram(bool $oddSemester): array
    {
        $blocked = [];

        $this->placedGroups($oddSemester)->with('studyPrograms')->get()
            ->each(function (TpbGroup $group) use (&$blocked) {
                foreach ($group->studyPrograms as $program) {
                    $blocked[$program->id][$group->course->semester][] = $group->time_slot_id;
                }
            });

        return $this->unique($blocked, depth: 2);
    }

    /**
     * @return array<int, list<int>> lecturer_id => time_slot_ids
     */
    public function blockedSlotsByLecturer(bool $oddSemester): array
    {
        return $this->classSlots($oddSemester, 'lecturer_id');
    }

    /**
     * @return array<int, list<int>> room_id => time_slot_ids
     */
    public function blockedSlotsByRoom(bool $oddSemester): array
    {
        return $this->classSlots($oddSemester, 'room_id');
    }

    /**
     * @return Builder<TpbGroup>
     */
    private function placedGroups(bool $oddSemester): Builder
    {
        return TpbGroup::query()
            ->with('course')
            ->whereNotNull('time_slot_id')
            ->whereHas('course', fn (Builder $query) => $query->whereRaw('semester % 2 = ?', [$oddSemester ? 1 : 0]));
    }

    /**
     * @return array<int, list<int>>
     */
    private function classSlots(bool $oddSemester, string $column): array
    {
        $blocked = [];

        CourseLecturer::query()
            ->with('tpbGroup')
            ->whereNotNull($column)
            ->whereHas('tpbGroup', fn (Builder $query) => $query->whereIn('id', $this->placedGroups($oddSemester)->select('id')))
            ->get()
            ->each(function (CourseLecturer $class) use (&$blocked, $column) {
                $blocked[$class->{$column}][] = $class->tpbGroup->time_slot_id;
            });

        return $this->unique($blocked, depth: 1);
    }

    /**
     * @param  array<int, mixed>  $map
     * @return array<int, mixed>
     */
    private function unique(array $map, int $depth): array
    {
        return array_map(
            fn (array $value) => $depth > 1 ? $this->unique($value, $depth - 1) : array_values(array_unique($value)),
            $map,
        );
    }
}
