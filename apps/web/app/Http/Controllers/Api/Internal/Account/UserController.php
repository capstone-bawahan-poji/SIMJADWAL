<?php

namespace App\Http\Controllers\Api\Internal\Account;

use App\Data\Account\UserFormData;
use App\Enums\Account\Role;
use App\Http\Controllers\Api\ApiController;
use App\Http\Queries\Query;
use App\Http\Requests\Account\User\ListUserRequest;
use App\Http\Requests\Account\User\StoreUserRequest;
use App\Http\Requests\Account\User\UpdateUserRequest;
use App\Http\Requests\Account\User\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\Account\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends ApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.User::class, only: ['index']),
            new Middleware('can:view,user', only: ['show']),
            new Middleware('can:create,'.User::class, only: ['store']),
            new Middleware('can:update,user', only: ['update']),
            new Middleware('can:updateStatus,user', only: ['updateStatus']),
        ];
    }

    public function __construct(
        private readonly Request $request,
        private readonly UserService $userService,
    ) {}

    public function index(ListUserRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->userService->getUsers(
            search: $v['q'] ?? null,
            role: isset($v['role']) ? Role::from($v['role']) : null,
            isActive: isset($v['is_active']) ? (bool) $v['is_active'] : null,
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            perPage: (int) ($v['per_page'] ?? Query::DEFAULT_PER_PAGE),
        ));
    }

    public function show(User $user): JsonResponse
    {
        return $this->response($this->userService->getUser($user));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $v = $request->validated();
        $formData = new UserFormData(
            name: $v['name'],
            email: $v['email'],
            identityNumber: $v['identity_number'] ?? null,
            password: $v['password'] ?? null,
            role: Role::from($v['role']),
            facultyId: isset($v['faculty_id']) ? (int) $v['faculty_id'] : null,
            studyProgramId: isset($v['study_program_id']) ? (int) $v['study_program_id'] : null,
            lecturerId: isset($v['lecturer_id']) ? (int) $v['lecturer_id'] : null,
        );

        return $this->response($this->userService->createUser($formData), 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $v = $request->validated();
        $role = isset($v['role']) ? Role::from($v['role']) : $user->role;

        // Absent fields keep their current value; scope ids outside the role are cleared.
        $formData = new UserFormData(
            name: $v['name'] ?? $user->name,
            email: $v['email'] ?? $user->email,
            identityNumber: $role->requiresLecturer() ? null : (array_key_exists('identity_number', $v) ? $v['identity_number'] : $user->getRawOriginal('identity_number')),
            password: $v['password'] ?? null,
            role: $role,
            facultyId: $role->requiresFaculty() ? (int) ($v['faculty_id'] ?? $user->faculty_id) : null,
            studyProgramId: $role->requiresStudyProgram() ? (int) ($v['study_program_id'] ?? $user->study_program_id) : null,
            lecturerId: $role->requiresLecturer() ? (int) ($v['lecturer_id'] ?? $user->lecturer?->id) : null,
        );

        return $this->response($this->userService->updateUser($user, $formData));
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        return $this->response($this->userService->updateStatus(
            $user,
            $request->boolean('is_active'),
            $this->request->user(),
        ));
    }
}
