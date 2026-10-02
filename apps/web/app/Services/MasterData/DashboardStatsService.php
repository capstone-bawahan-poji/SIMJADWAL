<?php

namespace App\Services\MasterData;

use App\Enums\Account\Role;
use App\Models\MasterData\Department;
use App\Models\MasterData\Faculty;
use App\Models\MasterData\Room;
use App\Models\MasterData\StudyProgram;
use App\Models\MasterData\TimeSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Counts for the superadmin dashboard, computed in one page load instead of one request per card.
 */
readonly class DashboardStatsService
{
    /**
     * @return array{
     *     faculties: int, departments: int, study_programs: int,
     *     users: array{total: int, active: int, by_role: array<string, int>},
     *     rooms: array{total: int, shared: int},
     *     time_slots: array{total: int, days: int, sessions_per_day: int},
     * }
     */
    public function get(): array
    {
        $byRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role, count(*) as total')
            ->pluck('total', 'role');

        return [
            'faculties' => Faculty::query()->count(),
            'departments' => Department::query()->count(),
            'study_programs' => StudyProgram::query()->count(),
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'by_role' => collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => (int) ($byRole[$role->value] ?? 0)])->all(),
            ],
            'rooms' => [
                'total' => Room::query()->count(),
                'shared' => Room::query()->whereNull('faculty_id')->count(),
            ],
            'time_slots' => [
                'total' => TimeSlot::query()->count(),
                'days' => TimeSlot::query()->distinct()->count('day'),
                'sessions_per_day' => (int) TimeSlot::query()->max('session'),
            ],
        ];
    }
}
