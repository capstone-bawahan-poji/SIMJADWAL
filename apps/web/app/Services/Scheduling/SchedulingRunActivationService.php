<?php

namespace App\Services\Scheduling;

use App\Enums\Scheduling\RunStatus;
use App\Exceptions\Shared\ApiException;
use App\Models\Scheduling\ProgramScheduleSubmission;
use App\Models\Scheduling\SchedulingRun;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Activating picks the run that is the schedule of its study program (or of TPB) in the period.
 *
 *   - Program run: activated by the program while its schedule is still a draft, and only when it
 *     still fits the current data (ProgramRunValidator). It becomes the run of the program's
 *     submission. The faculty publishes program runs (SchedulePublicationService), never this service.
 *   - TPB run: activated and then published by the TPB admin, as before. A run that loses its
 *     active flag keeps published_at, which the archived scope relies on.
 */
readonly class SchedulingRunActivationService
{
    public function __construct(
        private DatabaseManager $db,
        private TpbRunResolver $tpbRuns,
        private ProgramRunValidator $validator,
        private ProgramScheduleState $state,
    ) {}

    public function activate(User $actor, SchedulingRun $run): SchedulingRun
    {
        if ($run->is_active) {
            return $run;
        }
        if ($run->status !== RunStatus::DONE) {
            throw ApiException::invalidState(__('Only a finished run can be activated.'));
        }
        if (! $run->isTpb() && $run->study_program_id === null) {
            throw ApiException::invalidState(__('Faculty-wide runs are no longer used. Each study program runs its own GA.'));
        }
        if (! $run->isTpb()) {
            $this->state->assertEditable($run->studyProgram, $run->period);
        }

        $this->db->transaction(function () use ($run) {
            // Serialises activations in the period: two programs sharing a lecturer must not both pass the check.
            $current = SchedulingRun::query()->active()
                ->where('period', $run->period)
                ->lockForUpdate()
                ->get()
                ->filter(fn (SchedulingRun $active) => $active->faculty_id === $run->faculty_id && $active->study_program_id === $run->study_program_id);

            if (! $run->isTpb()) {
                $this->validator->assertValid($run);
            }

            $current->each(fn (SchedulingRun $old) => $old->update(['is_active' => false]));
            $run->update(['is_active' => true]);

            if (! $run->isTpb()) {
                ProgramScheduleSubmission::query()->updateOrCreate(
                    ['study_program_id' => $run->study_program_id, 'period' => $run->period],
                    ['scheduling_run_id' => $run->id],
                );
            }
        });
        $this->tpbRuns->forget();

        activity('scheduling')->causedBy($actor)->performedOn($run)->event('scheduling_run.activated')
            ->log(__('Activated scheduling run :id', ['id' => $run->id]));

        return $run;
    }

    /**
     * TPB only. A program schedule is published with the rest of its faculty.
     */
    public function publish(User $actor, SchedulingRun $run): SchedulingRun
    {
        if (! $run->isTpb()) {
            throw ApiException::invalidState(__('A study program schedule is published by its faculty, together with the other programs.'));
        }
        if (! $run->is_active) {
            throw ApiException::invalidState(__('Only the active schedule can be published.'));
        }
        if ($run->published_at !== null) {
            return $run;
        }

        $run->update(['published_by' => $actor->id, 'published_at' => now()]);
        $this->tpbRuns->forget();

        activity('scheduling')->causedBy($actor)->performedOn($run)->event('scheduling_run.published')
            ->log(__('Published scheduling run :id', ['id' => $run->id]));

        return $run;
    }
}
