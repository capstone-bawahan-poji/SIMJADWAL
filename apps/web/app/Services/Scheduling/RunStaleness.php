<?php

namespace App\Services\Scheduling;

use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\StudyProgram;
use App\Models\Scheduling\SchedulingRun;

/**
 * A run is stale when the data it was computed from has changed: for TPB-dependent runs the TPB
 * run in force, for program runs also the dataset (cells, courses, lecturers, preferences, other
 * programs' active runs). Stale is a warning; activation re-checks what actually matters.
 * One dataset build per program and period per request.
 */
class RunStaleness
{
    /** @var array<string, string|null> "program:period" => fingerprint, null = no dataset can be built */
    private array $fingerprints = [];

    public function __construct(
        private readonly TpbRunResolver $tpbRuns,
        private readonly SchedulingDatasetService $datasets,
    ) {}

    /**
     * Services live across requests (controllers are kept), so each listing starts over.
     */
    public function forget(): void
    {
        $this->fingerprints = [];
    }

    public function isStale(SchedulingRun $run): bool
    {
        if ($run->isTpb()) {
            return false;
        }
        if ($run->study_program_id === null) {
            return $this->tpbRuns->isStale($run);
        }

        $key = "{$run->study_program_id}:{$run->period}";
        if (! array_key_exists($key, $this->fingerprints)) {
            try {
                $program = StudyProgram::query()->findOrFail($run->study_program_id);
                $this->fingerprints[$key] = $this->datasets->build($program, $run->period, checkEditable: false)['fingerprint'];
            } catch (ApiException) {
                $this->fingerprints[$key] = null;
            }
        }

        return $this->fingerprints[$key] !== $run->dataset_fingerprint;
    }
}
