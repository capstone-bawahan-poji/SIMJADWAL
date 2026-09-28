<?php

namespace App\Http\Controllers\Web;

use Inertia\Inertia;

/**
 * Base for controllers that render an Inertia page.
 */
abstract class InertiaController extends Controller
{
    /**
     * Share which action buttons the current user may see, read on the page as the `actions` prop.
     *
     * @param  array<string, bool>  $actions
     */
    protected function shareActions(array $actions): void
    {
        Inertia::share('actions', $actions);
    }
}
