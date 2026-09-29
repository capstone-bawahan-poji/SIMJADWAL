<?php

namespace App\Http\Controllers\Web;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends InertiaController
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard');
    }
}
