<?php

namespace App\Http\Controllers\Api\V1\MasterData;

use App\Http\Controllers\Api\Internal\MasterData\AcademicPeriodController as InternalAcademicPeriodController;

/**
 * Same contract as the internal endpoint today. Override here when v1 has to diverge.
 */
class AcademicPeriodController extends InternalAcademicPeriodController {}