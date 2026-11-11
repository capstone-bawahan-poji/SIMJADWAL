<?php

namespace App\Services\MasterData;

use App\Data\MasterData\TimeSlotData;
use App\Data\MasterData\TimeSlotFormData;
use App\Enums\MasterData\ReferenceType;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\TimeSlot;
use Illuminate\Support\Collection;

/**
 * Slots follow the campus rule (sessions 1-4 per weekday) and rarely change.
 * A slot in use keeps its day and session: preferences, schedules and TPB groups read them.
 * Its start time, end time and type (SKS length) stay editable; the type change applies to
 * every preference and schedule that uses the slot, so those need a new GA run. A slot in use cannot be deleted.
 */
readonly class TimeSlotService
{
    public function __construct(
        private TimeSlot $timeSlot,
        private ReferenceGuard $referenceGuard,
    ) {}

    /**
     * Not paginated: 20 rows.
     *
     * @return Collection<int, TimeSlotData>
     */
    public function getTimeSlots(): Collection
    {
        return $this->timeSlot->newQuery()
            ->orderBy('day')
            ->orderBy('session')
            ->get()
            ->map(fn (TimeSlot $timeSlot) => $this->toData($timeSlot));
    }

    public function getTimeSlot(TimeSlot $timeSlot): TimeSlotData
    {
        return $this->toData($timeSlot);
    }

    public function createTimeSlot(TimeSlotFormData $data): TimeSlotData
    {
        $timeSlot = $this->timeSlot->newQuery()->create($this->attributes($data));

        return $this->getTimeSlot($timeSlot->fresh());
    }

    public function updateTimeSlot(TimeSlot $timeSlot, TimeSlotFormData $data): TimeSlotData
    {
        if ($data->day !== $timeSlot->day || $data->session !== $timeSlot->session) {
            $this->ensureUnused($timeSlot);
        }

        $timeSlot->update($this->attributes($data));

        return $this->getTimeSlot($timeSlot->fresh());
    }

    /**
     * Hard delete. MVP: schedules of every run count, including inactive history, because
     * schedule_details keeps a RESTRICT foreign key to the slot. If old runs should stop
     * blocking this, add soft deletes (or an is_active flag) to time_slots.
     */
    public function deleteTimeSlot(TimeSlot $timeSlot): void
    {
        if ($this->timeSlot->newQuery()->count() <= 1) {
            throw ApiException::invalidState(__('The last time slot cannot be deleted.'));
        }

        $this->ensureUnused($timeSlot);

        $timeSlot->delete();
    }

    private function toData(TimeSlot $timeSlot): TimeSlotData
    {
        $data = TimeSlotData::from($timeSlot);
        $data->inUse = $timeSlot->lecturerPreferences()->exists() || $timeSlot->scheduleDetails()->exists();

        return $data;
    }

    private function ensureUnused(TimeSlot $timeSlot): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::LECTURER_PREFERENCES->value => $timeSlot->lecturerPreferences()->count(),
            ReferenceType::SCHEDULES->value => $timeSlot->scheduleDetails()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TimeSlotFormData $data): array
    {
        return [
            'day' => $data->day,
            'session' => $data->session,
            'start_time' => $data->startTime,
            'end_time' => $data->endTime,
            'type' => $data->type,
        ];
    }
}
