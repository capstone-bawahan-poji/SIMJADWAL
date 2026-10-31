<?php

namespace App\Http\Controllers\Web;

use App\Enums\Account\Role;
use App\Services\Dashboard\FacultyDashboardService;
use App\Services\Dashboard\StudyProgramDashboardService;
use App\Services\MasterData\DashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends InertiaController
{
    public function __construct(
        private readonly DashboardStatsService $dashboardStatsService,
        private readonly FacultyDashboardService $facultyDashboard,
        private readonly StudyProgramDashboardService $studyProgramDashboard,
    ) {}

    /**
     * Prodi and faculty admins get their own dashboard props (dashboard). stats is null for the
     * other roles until their dashboards exist. Props are closures so reload({ only }) stays cheap.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return match (true) {
            $user->hasRole(Role::STUDY_PROGRAM_ADMIN->value) => Inertia::render('Prodi/Dashboard', [
                'dashboard' => fn () => $this->studyProgramDashboard->get($user->studyProgram),
            ]),
            $user->hasRole(Role::FACULTY_ADMIN->value) => Inertia::render('Fakultas/Dashboard', [
                'dashboard' => fn () => $this->facultyDashboard->get($user->faculty_id),
            ]),
            default => Inertia::render('Dashboard', [
                'stats' => $user->hasRole(Role::SUPER_ADMIN->value) ? $this->dashboardStatsService->get() : null,
            ]),
        };
    }
}