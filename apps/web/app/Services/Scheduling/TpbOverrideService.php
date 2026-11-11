<?php

namespace App\Services\Scheduling;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\CourseLecturer;
use App\Models\MasterData\Room;
use App\Models\MasterData\TimeSlot;
use App\Models\MasterData\TpbGroup;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\Scheduling\ViolationLog;
use App\Models\User;
use App\Services\Shared\ChangeRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Manual changes to the TPB run while it is active and not published yet: move one block to
 * another slot, or swap the slots of two blocks. A block is one meeting of one TPB group, so all
 * its classes move together (HC-TPB-1). Rooms are never picked by hand: each class keeps its room
 * when it is free in the new slot, else takes the smallest free shared room that fits.
 *
 * Hard checks, as in the TPB GA: a lecturer teaches one class per slot (HC-TPB-2), a room holds
 * one class per slot (HC-TPB-3), capacity fits (HC-TPB-4), a program takes one regular group per
 * target semester per slot (HC-TPB-6), the two meetings of a 4 SKS course sit on different days
 * (HC-TPB-7). A slot whose length differs from the meeting's SKS is only a warning (SC_SKS).
 * Once the run is published faculties build on it, so nothing moves.
 */
readonly class TpbOverrideService
{
    public function __construct(
        private DatabaseManager $db,
        private ChangeRecorder $changes,
    ) {}

    /**
     * Every slot with whether the block may move there, the rooms it would get, and the blocks
     * of other groups it may swap with.
     *
     * @return array<string, mixed>
     */
    public function options(ScheduleDetail $detail): array
    {
        $run = $this->assertEditable($detail);
        $context = $this->context($run);
        $key = $this->blockKey($detail, $context['classes']);
        $block = $context['blocks'][$key];
        $rooms = $context['rooms']->keyBy('id');

        return [
            'block' => $this->describeBlock($block, $context),
            'current' => ['time_slot_id' => $block->first()->time_slot_id],
            'time_slots' => $context['slots']->values()->map(function (TimeSlot $slot) use ($context, $key, $block, $rooms) {
                $result = $this->check($context, $key, $slot->id);

                return [
                    'id' => $slot->id,
                    'day' => $slot->day->value,
                    'session' => $slot->session,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'type' => $slot->type,
                    'available' => $result['reasons'] === [],
                    'reasons' => $result['reasons'],
                    'warnings' => $result['warnings'],
                    'rooms' => $block->map(fn (ScheduleDetail $d) => isset($result['rooms'][$d->id]) ? [
                        'schedule_detail_id' => $d->id,
                        'class_number' => $d->class_number,
                        'room_id' => $result['rooms'][$d->id],
                        'room_code' => $rooms[$result['rooms'][$d->id]]->code,
                        'capacity' => $rooms[$result['rooms'][$d->id]]->capacity,
                    ] : null)->filter()->values()->all(),
                ];
            })->all(),
            'swap_candidates' => $this->swapCandidates($context, $key),
        ];
    }

    /**
     * @return Collection<int, ScheduleDetail> the moved block
     */
    public function override(User $actor, ScheduleDetail $detail, int $timeSlotId, ?string $reason = null): Collection
    {
        $run = $this->assertEditable($detail);

        $block = $this->db->transaction(function () use ($actor, $detail, $run, $timeSlotId) {
            $this->lockPeriod($run);
            $context = $this->context($run);
            $key = $this->blockKey($detail, $context['classes']);
            $block = $context['blocks'][$key];

            if (! $context['slots']->has($timeSlotId) || $block->first()->time_slot_id === $timeSlotId) {
                throw ApiException::validation(['time_slot_id' => [__('The meeting is already in this slot.')]]);
            }
            $result = $this->check($context, $key, $timeSlotId);
            if ($result['reasons'] !== []) {
                throw ApiException::validation(['time_slot_id' => $result['reasons']]);
            }

            $this->clearRooms($block);
            $block->each(fn (ScheduleDetail $d) => $this->move($actor, $d, $timeSlotId, $result['rooms'][$d->id]));

            return $block->values();
        });

        $this->log($actor, $run, 'tpb_block.moved', ['schedule_detail_ids' => $block->pluck('id')->all(), 'time_slot_id' => $timeSlotId, 'reason' => $reason],
            __('Moved TPB meeting :course of run :run', ['course' => $block->first()->course->name, 'run' => $run->id]));

        return $block;
    }

    /**
     * Swaps the slots of two blocks of different groups. Each block re-picks its rooms.
     *
     * @return Collection<int, ScheduleDetail> the details of both blocks
     */
    public function swap(User $actor, ScheduleDetail $a, ScheduleDetail $b, ?string $reason = null): Collection
    {
        if ($a->id === $b->id || $a->scheduling_run_id !== $b->scheduling_run_id) {
            throw ApiException::validation(['b' => [__('Pick another meeting of the same schedule.')]]);
        }
        $run = $this->assertEditable($a);

        $moved = $this->db->transaction(function () use ($actor, $a, $b, $run) {
            $this->lockPeriod($run);
            $context = $this->context($run);
            $keyA = $this->blockKey($a, $context['classes']);
            $keyB = $this->blockKey($b, $context['classes']);
            $blockA = $context['blocks'][$keyA];
            $blockB = $context['blocks'][$keyB];
            [$slotA, $slotB] = [$blockA->first()->time_slot_id, $blockB->first()->time_slot_id];

            if ($keyA === $keyB || $this->groupOf($blockA, $context['classes'])?->id === $this->groupOf($blockB, $context['classes'])?->id) {
                throw ApiException::validation(['b' => [__('Pick a meeting of another group.')]]);
            }
            if ($slotA === $slotB) {
                throw ApiException::validation(['b' => [__('Pick a meeting in another slot.')]]);
            }

            $toB = $this->check($context, $keyA, $slotB, [$keyB]);
            $toA = $this->check($context, $keyB, $slotA, [$keyA]);
            $errors = array_filter(['a' => $toB['reasons'], 'b' => $toA['reasons']]);
            if ($errors !== []) {
                throw ApiException::validation($errors);
            }

            // uq_schedule_details_cell is checked per statement, so both blocks leave their cells first.
            $this->clearRooms($blockA);
            $this->clearRooms($blockB);
            $blockA->each(fn (ScheduleDetail $d) => $this->move($actor, $d, $slotB, $toB['rooms'][$d->id]));
            $blockB->each(fn (ScheduleDetail $d) => $this->move($actor, $d, $slotA, $toA['rooms'][$d->id]));

            return $blockA->concat($blockB)->values();
        });

        $this->log($actor, $run, 'tpb_block.swapped', ['schedule_detail_ids' => $moved->pluck('id')->all(), 'reason' => $reason],
            __('Swapped TPB meetings :a and :b of run :run', ['a' => $a->id, 'b' => $b->id, 'run' => $run->id]));

        return $moved;
    }

    /**
     * Blocks of other groups the block may swap slots with, in both directions.
     *
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function swapCandidates(array $context, string $key): array
    {
        $block = $context['blocks'][$key];
        $slot = $block->first()->time_slot_id;
        $group = $this->groupOf($block, $context['classes']);

        return $context['blocks']
            ->reject(fn (Collection $other, string $otherKey) => $otherKey === $key
                || $other->first()->time_slot_id === $slot
                || ($group !== null && $this->groupOf($other, $context['classes'])?->id === $group->id))
            ->filter(fn (Collection $other, string $otherKey) => $this->check($context, $key, $other->first()->time_slot_id, [$otherKey])['reasons'] === []
                && $this->check($context, $otherKey, $slot, [$key])['reasons'] === [])
            ->map(fn (Collection $other) => $this->describeBlock($other, $context) + ['time_slot_id' => $other->first()->time_slot_id])
            ->values()
            ->all();
    }

    /**
     * Why the block cannot sit in the slot, the rooms its classes would get, and soft warnings.
     * $ignore are blocks treated as gone, e.g. the other side of a swap.
     *
     * @param  array<string, mixed>  $context
     * @param  list<string>  $ignore
     * @return array{reasons: list<string>, warnings: list<string>, rooms: array<int, int>}
     */
    private function check(array $context, string $key, int $slotId, array $ignore = []): array
    {
        $block = $context['blocks'][$key];
        $slot = $context['slots'][$slotId];
        $classes = $context['classes'];
        $skip = array_flip([$key, ...$ignore]);
        $others = $context['blocks']->reject(fn (Collection $other, string $otherKey) => isset($skip[$otherKey]));
        $atSlot = $others->filter(fn (Collection $other) => $other->first()->time_slot_id === $slotId);
        $reasons = [];
        $warnings = [];

        $lecturers = $block->pluck('lecturer_id')->all();
        foreach ($atSlot->collapse() as $other) {
            if (in_array($other->lecturer_id, $lecturers, true)) {
                $reasons[] = __('The lecturer already teaches :course :class in this slot.', ScheduleSlotChecker::describe($other));
            }
        }

        $group = $this->groupOf($block, $classes);
        if ($group !== null) {
            $sameGroup = $others->filter(fn (Collection $other) => $this->groupOf($other, $classes)?->id === $group->id);
            if ($sameGroup->contains(fn (Collection $other) => $other->first()->time_slot_id === $slotId)) {
                $reasons[] = __('Another meeting of this group is already in this slot.');
            }
            if ($block->first()->course->sks === 4 && $sameGroup->contains(fn (Collection $other) => $context['slots'][$other->first()->time_slot_id]->day === $slot->day)) {
                $reasons[] = __('The two meetings of a 4 SKS course cannot be on the same day.');
            }
            if ($group->cohort->isRegular() && $atSlot->contains(fn (Collection $other) => $this->sharesRegularSemester($group, $this->groupOf($other, $classes)))) {
                $reasons[] = __('Another regular TPB group of the same program and semester is in this slot.');
            }
        }

        $assigned = $this->assignRooms($block, $atSlot->collapse(), $context);
        if ($assigned === null) {
            $reasons[] = __('Not enough free shared rooms for every class of the group in this slot.');
        }

        $meetingSks = $block->first()->course->sks === 4 ? 2 : $block->first()->course->sks;
        if ($slot->type !== $meetingSks) {
            $warnings[] = __('The slot length does not match the SKS of this meeting.');
        }

        return [
            'reasons' => array_values(array_unique($reasons)),
            'warnings' => $warnings,
            'rooms' => $reasons === [] ? $assigned : [],
        ];
    }

    /**
     * Largest class first: it keeps its current room when free, else takes the smallest free shared room that fits.
     *
     * @param  Collection<int, ScheduleDetail>  $block
     * @param  Collection<int, ScheduleDetail>  $takenBy  genes of other blocks in the slot
     * @param  array<string, mixed>  $context
     * @return array<int, int>|null schedule_detail_id => room_id, null when a class finds no room
     */
    private function assignRooms(Collection $block, Collection $takenBy, array $context): ?array
    {
        $taken = $takenBy->pluck('room_id')->filter()->flip()->all();
        $assigned = [];

        foreach ($block->sortByDesc(fn (ScheduleDetail $d) => $this->capacityOf($d, $context['classes'])) as $detail) {
            $capacity = $this->capacityOf($detail, $context['classes']);
            $fits = $context['rooms']->filter(fn (Room $room) => $room->capacity >= $capacity && ! isset($taken[$room->id]));
            $room = $fits->firstWhere('id', $detail->room_id) ?? $fits->sortBy([['capacity', 'asc'], ['code', 'asc']])->first();
            if ($room === null) {
                return null;
            }
            $taken[$room->id] = true;
            $assigned[$detail->id] = $room->id;
        }

        return $assigned;
    }

    private function sharesRegularSemester(TpbGroup $group, ?TpbGroup $other): bool
    {
        if ($other === null || $other->id === $group->id || ! $other->cohort->isRegular()) {
            return false;
        }
        $mine = $group->studyPrograms->filter(fn ($p) => $p->pivot->target_semester !== null)->mapWithKeys(fn ($p) => [$p->id => (int) $p->pivot->target_semester]);

        return $other->studyPrograms->contains(fn ($p) => $p->pivot->target_semester !== null && ($mine[$p->id] ?? null) === (int) $p->pivot->target_semester);
    }

    /**
     * @param  Collection<int, ScheduleDetail>  $block
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function describeBlock(Collection $block, array $context): array
    {
        $first = $block->first();
        $rooms = $context['rooms']->keyBy('id');

        return [
            'schedule_detail_id' => $first->id,
            'course' => ['id' => $first->course_id, 'code' => $first->course->code, 'name' => $first->course->name],
            'group_code' => $this->groupOf($block, $context['classes'])?->code,
            'meeting_number' => $first->meeting_number,
            'classes' => $block->map(fn (ScheduleDetail $d) => [
                'schedule_detail_id' => $d->id,
                'class_number' => $d->class_number,
                'room_id' => $d->room_id,
                'room_code' => $d->room_id === null ? null : ($rooms[$d->room_id]->code ?? $d->room?->code),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<string, CourseLecturer>  $classes
     */
    private function blockKey(ScheduleDetail $detail, Collection $classes): string
    {
        $groupId = $classes->get("{$detail->course_id}:{$detail->class_number}")?->tpb_group_id;

        return $groupId === null ? "d{$detail->id}" : "g{$groupId}:{$detail->meeting_number}";
    }

    /**
     * @param  Collection<int, ScheduleDetail>  $block
     * @param  Collection<string, CourseLecturer>  $classes
     */
    private function groupOf(Collection $block, Collection $classes): ?TpbGroup
    {
        $first = $block->first();

        return $classes->get("{$first->course_id}:{$first->class_number}")?->tpbGroup;
    }

    /**
     * @param  Collection<string, CourseLecturer>  $classes
     */
    private function capacityOf(ScheduleDetail $detail, Collection $classes): int
    {
        return (int) ($classes->get("{$detail->course_id}:{$detail->class_number}")?->tpbOffering?->class_capacity ?? 0);
    }

    /**
     * Everything the checks read, loaded once: the genes of the run grouped in blocks, the TPB
     * classes with their groups, the slots and the shared rooms.
     *
     * @return array{blocks: Collection<string, Collection<int, ScheduleDetail>>, classes: Collection<string, CourseLecturer>, slots: Collection<int, TimeSlot>, rooms: Collection<int, Room>}
     */
    private function context(SchedulingRun $run): array
    {
        $classes = CourseLecturer::query()
            ->with(['tpbGroup.studyPrograms:id', 'tpbOffering:id,class_capacity'])
            ->whereRelation('tpbOffering', 'period', $run->period)
            ->get()
            ->keyBy(fn (CourseLecturer $class) => "{$class->course_id}:{$class->class_number}");

        $blocks = ScheduleDetail::query()
            ->with(['course:id,code,name,sks', 'room:id,code,name,capacity,faculty_id'])
            ->where('scheduling_run_id', $run->id)
            ->orderBy('class_number')
            ->get()
            ->groupBy(fn (ScheduleDetail $detail) => $this->blockKey($detail, $classes));

        return [
            'blocks' => $blocks,
            'classes' => $classes,
            'slots' => TimeSlot::query()->orderBy('day')->orderBy('session')->get()->keyBy('id'),
            'rooms' => Room::query()->whereNull('faculty_id')->get(['id', 'code', 'name', 'capacity', 'faculty_id']),
        ];
    }

    /**
     * @param  Collection<int, ScheduleDetail>  $block
     */
    private function clearRooms(Collection $block): void
    {
        $block->each(fn (ScheduleDetail $d) => $d->update(['room_id' => null]));
    }

    private function move(User $actor, ScheduleDetail $detail, int $timeSlotId, int $roomId): void
    {
        $detail->update([
            'time_slot_id' => $timeSlotId,
            'room_id' => $roomId,
            'is_overridden' => true,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
        ]);
        // Penalties were computed for the old place and no longer hold.
        ViolationLog::query()->where('schedule_detail_id', $detail->id)->delete();
        $this->changes->record('schedule_details', $detail->id);
        $this->changes->record('violation_logs');
    }

    /**
     * Serialises changes to the TPB run so two admins cannot take the same slot or room.
     */
    private function lockPeriod(SchedulingRun $run): void
    {
        SchedulingRun::query()->active()->whereNull('faculty_id')->where('period', $run->period)->lockForUpdate()->get();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(User $actor, SchedulingRun $run, string $event, array $properties, string $message): void
    {
        activity('scheduling')->causedBy($actor)->performedOn($run)->event($event)->withProperties($properties)->log($message);
    }

    private function assertEditable(ScheduleDetail $detail): SchedulingRun
    {
        $run = $detail->schedulingRun;
        if (! $run->isTpb()) {
            throw ApiException::invalidState(__('Only the TPB schedule is changed here.'));
        }
        if (! $run->is_active) {
            throw ApiException::invalidState(__('Only the active schedule can be overridden.'));
        }
        if ($run->published_at !== null) {
            throw ApiException::invalidState(__('The TPB schedule is published. Run the GA again to change it.'));
        }

        return $run;
    }
}
