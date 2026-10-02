<?php

namespace App\Models\Account;

use App\Policies\Account\ActivityLogPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Spatie\Activitylog\Models\Activity;

/**
 * One audit entry. attribute_changes holds {old, attributes} of the fields that changed.
 */
#[UsePolicy(ActivityLogPolicy::class)]
class ActivityLog extends Activity
{
    protected $table = 'activity_logs';
}
