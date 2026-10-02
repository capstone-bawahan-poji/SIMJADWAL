<?php

namespace App\Services\Account;

use App\Data\Account\UserData;
use App\Data\Account\UserFormData;
use App\Enums\Account\Role;
use App\Exceptions\Shared\ApiException;
use App\Models\MasterData\Lecturer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

readonly class UserService
{
    public function __construct(
        private User $user,
        private Lecturer $lecturer,
        private DatabaseManager $db,
        private ActivityLogService $activityLog,
    ) {}

    /**
     * faculty_id and study_program_id match the account's own scope or, for lecturers, the
     * homebase of the linked lecturer. Superadmin and TPB admin accounts have no scope.
     *
     * @return LengthAwarePaginator<int, UserData>
     */
    public function getUsers(?string $search, ?Role $role, ?bool $isActive, ?int $facultyId, ?int $studyProgramId, int $perPage): LengthAwarePaginator
    {
        $users = UserData::prepareQuery($this->user->newQuery())
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereLike('identity_number', "%{$search}%")
                ->orWhereRelation('lecturer', 'nip', 'like', "%{$search}%")))
            ->when($role, fn (Builder $query) => $query->role($role->value))
            ->when($isActive !== null, fn (Builder $query) => $query->where('is_active', $isActive))
            ->when($facultyId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('faculty_id', $facultyId)
                ->orWhereRelation('studyProgram', 'faculty_id', $facultyId)
                ->orWhereRelation('lecturer.studyProgram', 'faculty_id', $facultyId)))
            ->when($studyProgramId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('study_program_id', $studyProgramId)
                ->orWhereRelation('lecturer', 'study_program_id', $studyProgramId)))
            ->orderBy('name')
            ->paginate($perPage);

        return $users->through(fn (User $user) => UserData::from($user));
    }

    public function getUser(User $user): UserData
    {
        return UserData::from(UserData::loadRelations($user));
    }

    public function createUser(UserFormData $data): UserData
    {
        $user = $this->db->transaction(function () use ($data) {
            $user = $this->user->newQuery()->create([
                'name' => $data->name,
                'email' => $data->email,
                'identity_number' => $data->identityNumber,
                'password' => $data->password ?? config('accounts.default_password'),
                'faculty_id' => $data->facultyId,
                'study_program_id' => $data->studyProgramId,
            ]);

            $user->syncRoles([$data->role->value]);
            $this->syncLecturer($user, $data->lecturerId);
            $this->activityLog->userCreated($user);

            return $user;
        });

        return $this->getUser($user->fresh());
    }

    public function updateUser(User $user, UserFormData $data): UserData
    {
        $this->db->transaction(function () use ($user, $data) {
            $before = $this->activityLog->snapshot($user);

            $user->fill([
                'name' => $data->name,
                'email' => $data->email,
                'identity_number' => $data->identityNumber,
                'faculty_id' => $data->facultyId,
                'study_program_id' => $data->studyProgramId,
            ]);

            if ($data->password !== null) {
                $user->password = $data->password;
            }

            $user->save();
            $user->syncRoles([$data->role->value]);
            $this->syncLecturer($user, $data->lecturerId);
            $this->activityLog->userUpdated($user, $before, passwordChanged: $data->password !== null);
        });

        return $this->getUser($user->fresh());
    }

    /**
     * Self-service edit from the profile page. Role and scope stay with the admin flow.
     */
    public function updateProfile(User $user, string $name, string $email): UserData
    {
        $user->update(['name' => $name, 'email' => $email]);

        return $this->getUser($user);
    }

    /**
     * Deactivating revokes every API token of the account. Web sessions are
     * ended on their next request by the EnsureUserIsActive middleware.
     */
    public function updateStatus(User $user, bool $isActive, User $actor): UserData
    {
        if (! $isActive && $user->is($actor)) {
            throw ApiException::validation(['is_active' => [__('You cannot deactivate your own account.')]]);
        }

        $this->db->transaction(function () use ($user, $isActive) {
            $wasActive = $user->is_active;
            $user->update(['is_active' => $isActive]);
            $this->activityLog->userStatusChanged($user, $wasActive);

            if (! $isActive) {
                $user->tokens()->delete();
            }
        });

        return $this->getUser($user->fresh());
    }

    /**
     * Keep lecturers.user_id pointing at this account only when the account is a lecturer.
     */
    private function syncLecturer(User $user, ?int $lecturerId): void
    {
        $this->lecturer->newQuery()
            ->where('user_id', $user->id)
            ->when($lecturerId, fn (Builder $query) => $query->whereKeyNot($lecturerId))
            ->update(['user_id' => null]);

        if ($lecturerId !== null) {
            $this->lecturer->newQuery()->whereKey($lecturerId)->update(['user_id' => $user->id]);
        }
    }
}
