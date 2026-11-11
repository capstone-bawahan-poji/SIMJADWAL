<?php

namespace App\Models\Scheduling;

use App\Enums\Scheduling\SubmissionStatus;
use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The schedule of one study program in one period: which run it chose and where the review stands.
 * No row = draft.
 */
#[Fillable([
    'period', 'study_program_id', 'scheduling_run_id', 'status', 'submitted_by', 'submitted_at',
    'reviewed_by', 'reviewed_at', 'return_note',
])]
class ProgramScheduleSubmission extends Model
{
    use BroadcastsChanges;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StudyProgram, $this> */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /** @return BelongsTo<SchedulingRun, $this> */
    public function schedulingRun(): BelongsTo
    {
        return $this->belongsTo(SchedulingRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
