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
     * Prodi and faculty admins have their own dashboard pages. stats is null for the other
     * roles until their dashboards exist.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return match (true) {
            $user->hasRole(Role::STUDY_PROGRAM_ADMIN->value) => Inertia::render('Prodi/Dashboard'),
            $user->hasRole(Role::FACULTY_ADMIN->value) => Inertia::render('Fakultas/Dashboard'),
            default => Inertia::render('Dashboard', [
                'stats' => $user->hasRole(Role::SUPER_ADMIN->value) ? $this->dashboardStatsService->get() : null,
            ]),
        };
    }
}
