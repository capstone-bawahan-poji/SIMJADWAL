<?php

namespace App\Models\Scheduling;

use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\MasterData\Faculty;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The TPB schedule a faculty imported from a file, used while TPB has no published schedule in
 * the system. Seen by that faculty only.
 */
#[Fillable(['faculty_id', 'period', 'file_name', 'imported_by'])]
class TpbOccupancyImport extends Model
{
    use BroadcastsChanges;

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /** @return HasMany<TpbOccupancyImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(TpbOccupancyImportRow::class);
    }
}
