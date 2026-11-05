<?php

namespace App\Models\MasterData;

use App\Extensions\Models\Traits\BroadcastsChanges;
use App\Models\Scheduling\SchedulingRun;
use App\Policies\MasterData\AcademicPeriodPolicy;
use Database\Factories\MasterData\AcademicPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * First and last lecture day of a period (code YYYY/YYYY-GANJIL|GENAP). The .ics export
 * repeats each class weekly between these dates.
 *
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
#[Fillable(['code', 'start_date', 'end_date'])]
#[UsePolicy(AcademicPeriodPolicy::class)]
class AcademicPeriod extends Model
{
    /** @use HasFactory<AcademicPeriodFactory> */
    use BroadcastsChanges, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return HasMany<SchedulingRun, $this> */
    public function schedulingRuns(): HasMany
    {
        return $this->hasMany(SchedulingRun::class, 'period', 'code');
    }
}
