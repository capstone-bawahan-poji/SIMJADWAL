<?php

namespace App\Models\Scheduling;

use App\Enums\Scheduling\SlotRequestStatus;
use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\StudyProgram;
use App\Models\User;
use App\Policies\Scheduling\SlotRequestPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A study program asks its faculty for more cells (POS steps 4-6).
 * needs: list of {course_id, meetings, min_capacity}.
 */
#[Fillable(['period', 'study_program_id', 'requested_by', 'status', 'needs', 'note', 'response_note', 'reviewed_by', 'reviewed_at'])]
#[UsePolicy(SlotRequestPolicy::class)]
class SlotRequest extends Model
{
    use BroadcastsChanges;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => SlotRequestStatus::class,
            'needs' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StudyProgram, $this> */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<RoomSlotAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(RoomSlotAllocation::class);
    }
}
