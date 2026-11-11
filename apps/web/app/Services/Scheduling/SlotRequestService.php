<?php

namespace App\Services\Scheduling;

use App\Contracts\Account\ResolvesOrgScope;
use App\Enums\Scheduling\SlotRequestStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Course;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\SlotRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * POS steps 4-6. A program asks for more cells; the faculty answers with cells (free ones, or
 * cells taken from another program of the faculty) or rejects with a note. Asking TPB Pusat when
 * the faculty has no cell left (step 7) happens outside the system; the note records it.
 */
readonly class SlotRequestService
{
    public function __construct(
        private DatabaseManager $db,
        private ResolvesOrgScope $orgScope,
        private ProgramScheduleState $state,
        private SlotSufficiencyService $sufficiency,
        private RoomSlotAllocationService $allocations,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function list(User $actor, ?string $period, ?SlotRequestStatus $status, ?int $studyProgramId, int $perPage): LengthAwarePaginator
    {
        return SlotRequest::query()
            ->with(['studyProgram:id,code,name,faculty_id', 'requestedBy:id,name', 'reviewedBy:id,name'])
            ->withCount('allocations')
            ->tap(fn (Builder $query) => $this->orgScope->applyStudyProgramScope($query, $actor))
            ->when($period, fn (Builder $q) => $q->where('period', $period))
            ->when($status, fn (Builder $q) => $q->where('status', $status->value))
            ->when($studyProgramId, fn (Builder $q) => $q->where('study_program_id', $studyProgramId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (SlotRequest $request) => $this->toArray($request));
    }

    /**
     * $needs empty = the shortages of the sufficiency check.
     *
     * @param  list<array{course_id: int, meetings: int, min_capacity: int}>  $needs
     */
    public function create(User $actor, StudyProgram $studyProgram, string $period, array $needs, ?string $note): array
    {
        $this->state->assertEditable($studyProgram, $period);

        if ($needs === []) {
            $needs = array_map(fn (array $short) => [
                'course_id' => $short['course_id'],
                'meetings' => $short['meetings'],
                'min_capacity' => $short['min_capacity'],
            ], $this->sufficiency->check($studyProgram, $period)['shortages']);
        }
        if ($needs === []) {
            throw ApiException::validation(['needs' => [__('The program\'s rooms already hold all its classes. List the needs to ask for more anyway.')]]);
        }

        try {
            // A savepoint, so a lost race leaves any surrounding transaction usable.
            $request = $this->db->transaction(fn () => SlotRequest::query()->create([
                'period' => $period,
                'study_program_id' => $studyProgram->id,
                'requested_by' => $actor->id,
                'status' => SlotRequestStatus::PENDING,
                'needs' => $needs,
                'note' => $note,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ApiException::invalidState(__('The program already has a pending request for this period.'));
        }

        activity('scheduling')->causedBy($actor)->performedOn($request)->event('slot_request.created')
            ->log(__('Requested more room slots for :program', ['program' => $studyProgram->code]));

        return $this->toArray($request->fresh(['studyProgram', 'requestedBy', 'reviewedBy']));
    }

    /**
     * @param  list<array{time_slot_id: int, room_id: int}>  $cells
     */
    public function fulfill(User $actor, SlotRequest $request, array $cells, ?string $responseNote): array
    {
        $this->assertPending($request);
        $program = $request->studyProgram;

        $this->db->transaction(function () use ($actor, $request, $cells, $responseNote, $program) {
            SlotRequest::query()->whereKey($request->id)->lockForUpdate()->first();
            $this->allocations->sync(
                $actor,
                $program->faculty,
                $request->period,
                array_map(fn (array $cell) => [...$cell, 'study_program_id' => $program->id], $cells),
                $request,
            );
            $request->update([
                'status' => $this->sufficiency->check($program, $request->period)['sufficient']
                    ? SlotRequestStatus::FULFILLED
                    : SlotRequestStatus::PARTIALLY_FULFILLED,
                'response_note' => $responseNote,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
        });

        activity('scheduling')->causedBy($actor)->performedOn($request)->event('slot_request.fulfilled')
            ->log(__('Gave :count room slots to :program', ['count' => count($cells), 'program' => $program->code]));

        return $this->toArray($request->fresh(['studyProgram', 'requestedBy', 'reviewedBy']));
    }

    public function reject(User $actor, SlotRequest $request, string $responseNote): array
    {
        $this->assertPending($request);
        $request->update([
            'status' => SlotRequestStatus::REJECTED,
            'response_note' => $responseNote,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        activity('scheduling')->causedBy($actor)->performedOn($request)->event('slot_request.rejected')
            ->log(__('Rejected the room slot request of :program', ['program' => $request->studyProgram->code]));

        return $this->toArray($request->fresh(['studyProgram', 'requestedBy', 'reviewedBy']));
    }

    public function cancel(User $actor, SlotRequest $request): void
    {
        $this->assertPending($request);
        $request->delete();

        activity('scheduling')->causedBy($actor)->event('slot_request.cancelled')
            ->withProperties(['slot_request_id' => $request->id])
            ->log(__('Cancelled the room slot request of :program', ['program' => $request->studyProgram->code]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(SlotRequest $request): array
    {
        return [
            'id' => $request->id,
            'period' => $request->period,
            'study_program' => ['id' => $request->studyProgram->id, 'code' => $request->studyProgram->code, 'name' => $request->studyProgram->name],
            'status' => $request->status->value,
            'needs' => $this->describeNeeds($request->needs),
            'note' => $request->note,
            'response_note' => $request->response_note,
            'allocations_count' => $request->allocations_count ?? $request->allocations()->count(),
            'requested_by' => $request->requestedBy ? ['id' => $request->requestedBy->id, 'name' => $request->requestedBy->name] : null,
            'reviewed_by' => $request->reviewedBy ? ['id' => $request->reviewedBy->id, 'name' => $request->reviewedBy->name] : null,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array{course_id: int, meetings: int, min_capacity: int}>  $needs
     * @return list<array{course_id: int, code: string|null, name: string|null, sks: int|null, slot_type: int|null, meetings: int, min_capacity: int}>
     */
    private function describeNeeds(array $needs): array
    {
        $courses = Course::query()->whereIn('id', array_column($needs, 'course_id'))->get(['id', 'code', 'name', 'sks'])->keyBy('id');

        return array_map(fn (array $need) => [
            'course_id' => (int) $need['course_id'],
            'code' => $courses[$need['course_id']]->code ?? null,
            'name' => $courses[$need['course_id']]->name ?? null,
            'sks' => $courses[$need['course_id']]->sks ?? null,
            'slot_type' => isset($courses[$need['course_id']]) ? SlotSufficiencyService::slotType($courses[$need['course_id']]->sks) : null,
            'meetings' => (int) $need['meetings'],
            'min_capacity' => (int) $need['min_capacity'],
        ], $needs);
    }

    private function assertPending(SlotRequest $request): void
    {
        if ($request->status !== SlotRequestStatus::PENDING) {
            throw ApiException::invalidState(__('The request was already answered.'));
        }
    }
}
