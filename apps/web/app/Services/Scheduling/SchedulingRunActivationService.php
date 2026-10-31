<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\Scheduling\ScheduleDetail;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Activate and publish are two steps. Activating makes a run the official schedule of its
 * faculty and period, still hidden from lecturers and students. Publishing then shows it to them.
 * A run that loses its active flag keeps published_at, which the archived scope relies on.
 */
readonly class SchedulingRunActivationService
{
    public function __construct(
        private DatabaseManager $db,
        private ScheduleSlotCheckerFactory $checkers,
    ) {}

    public function activate(User $actor, SchedulingRun $run): SchedulingRun
    {
        if ($run->is_active) {
            return $run;
        }
        if ($run->status !== RunStatus::DONE) {
            throw ApiException::invalidState(__('Only a finished run can be activated.'));
        }

        $this->db->transaction(function () use ($run) {
            // Serialises activations of one faculty and period, and covers the clash check below.
            $current = SchedulingRun::query()->active()
                ->where('faculty_id', $run->faculty_id)
                ->where('period', $run->period)
                ->lockForUpdate()
                ->get();

            $this->assertNoClashWithOtherFaculties($run);

            $current->each(fn (SchedulingRun $old) => $old->update(['is_active' => false]));
            $run->update(['is_active' => true]);
        });

        activity('scheduling')->causedBy($actor)->performedOn($run)->event('scheduling_run.activated')
            ->log(__('Activated scheduling run :id', ['id' => $run->id]));

        return $run;
    }

    public function publish(User $actor, SchedulingRun $run): SchedulingRun
    {
        if (! $run->is_active) {
            throw ApiException::invalidState(__('Only the active schedule can be published.'));
        }
        if ($run->published_at !== null) {
            return $run;
        }

        $run->update(['published_by' => $actor->id, 'published_at' => now()]);

        activity('scheduling')->causedBy($actor)->performedOn($run)->event('scheduling_run.published')
            ->log(__('Published scheduling run :id', ['id' => $run->id]));

        return $run;
    }

    /**
     * Another faculty may have activated a schedule after this run was computed. Shared rooms and
     * lecturers who teach in several faculties must not be double-booked across the two.
     */
    private function assertNoClashWithOtherFaculties(SchedulingRun $run): void
    {
        $others = $this->checkers->otherFacultiesOccupancy($run);
        $genes = $run->scheduleDetails()->get(['time_slot_id', 'lecturer_id', 'room_id']);

        $roomClashes = $genes->filter(fn (ScheduleDetail $g) => $others->roomClash($g->time_slot_id, $g->room_id) !== null)->count();
        $lecturerClashes = $genes->filter(fn (ScheduleDetail $g) => $others->lecturerClash($g->time_slot_id, $g->lecturer_id) !== null)->count();

        if ($roomClashes > 0 || $lecturerClashes > 0) {
            throw ApiException::invalidState(__('This run clashes with the active schedule of another faculty (:rooms room and :lecturers lecturer clashes). Reload the data and run again.', [
                'rooms' => $roomClashes,
                'lecturers' => $lecturerClashes,
            ]));
        }
    }
}