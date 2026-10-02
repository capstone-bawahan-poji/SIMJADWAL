<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\Internal\Account\ActivityLogController as InternalActivityLogController;

/**
 * Same contract as the internal endpoint today. Override here when v1 has to diverge.
 */
class ActivityLogController extends InternalActivityLogController {}
