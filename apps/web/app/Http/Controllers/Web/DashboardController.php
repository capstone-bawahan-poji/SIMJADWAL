<?php

namespace App\Http\Controllers\Web;

use App\Enums\Account\Role;
use App\Services\MasterData\DashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends InertiaController
{
    public function __construct(private readonly DashboardStatsService $dashboardStatsService) {}

    /**
     * stats is null for other roles until their dashboards exist.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $request->user()->hasRole(Role::SUPER_ADMIN->value) ? $this->dashboardStatsService->get() : null,
        ]);
    }
}
