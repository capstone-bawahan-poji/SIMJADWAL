<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base for controllers behind routes/web.php: pages, redirects and session flows.
 * Business data for tables and CRUD goes through Api\Internal instead.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
