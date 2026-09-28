<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\Internal\Account\UserController as InternalUserController;

/**
 * The v1 contract (docs/api/openapi.yaml) matches the internal endpoint today.
 * Override a method here when v1 has to diverge, so the web frontend can change freely.
 */
class UserController extends InternalUserController {}
