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
 * A slot in use can be neither edited nor deleted: preferences, schedules and TPB
 * groups all read its day, time and type.
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
            ->map(fn (TimeSlot $timeSlot) => TimeSlotData::from($timeSlot));
    }

    public function getTimeSlot(TimeSlot $timeSlot): TimeSlotData
    {
        return TimeSlotData::from($timeSlot);
    }

    public function createTimeSlot(TimeSlotFormData $data): TimeSlotData
    {
        $timeSlot = $this->timeSlot->newQuery()->create($this->attributes($data));

        return $this->getTimeSlot($timeSlot->fresh());
    }

    public function updateTimeSlot(TimeSlot $timeSlot, TimeSlotFormData $data): TimeSlotData
    {
        $this->ensureUnused($timeSlot);

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

    private function ensureUnused(TimeSlot $timeSlot): void
    {
        $this->referenceGuard->ensureUnused([
            ReferenceType::LECTURER_PREFERENCES->value => $timeSlot->lecturerPreferences()->count(),
            ReferenceType::TPB_GROUPS->value => $timeSlot->tpbGroups()->count(),
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
