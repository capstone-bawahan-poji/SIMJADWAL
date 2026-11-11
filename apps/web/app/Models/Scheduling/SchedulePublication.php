<?php

namespace App\Models\Scheduling;

use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\Faculty;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The final schedule of one faculty in one period. checksum hashes the published rows, so
 * integration clients can tell when to pull again.
 */
#[Fillable(['faculty_id', 'period', 'published_by', 'published_at', 'checksum'])]
class SchedulePublication extends Model
{
    use BroadcastsChanges;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
