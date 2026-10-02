<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\Internal\MasterData\TimeSlotController as InternalTimeSlotController;

/**
 * Same contract as the internal endpoint today. Override here when v1 has to diverge.
 */
class TimeSlotController extends InternalTimeSlotController {}
