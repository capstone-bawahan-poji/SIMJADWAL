<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\Internal\MasterData\TeachingAssignmentController as InternalTeachingAssignmentController;

/**
 * Same contract as the internal endpoint today. Override here when v1 has to diverge.
 */
class TeachingAssignmentController extends InternalTeachingAssignmentController {}
