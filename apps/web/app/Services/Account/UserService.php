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
    ) {}

    /**
     * @return LengthAwarePaginator<int, UserData>
     */
    public function getUsers(?string $search, ?Role $role, ?bool $isActive, int $perPage): LengthAwarePaginator
    {
        $users = UserData::prepareQuery($this->user->newQuery())
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($role, fn (Builder $query) => $query->role($role->value))
            ->when($isActive !== null, fn (Builder $query) => $query->where('is_active', $isActive))
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
                'password' => $data->password,
                'faculty_id' => $data->facultyId,
                'study_program_id' => $data->studyProgramId,
            ]);

            $user->syncRoles([$data->role->value]);
            $this->syncLecturer($user, $data->lecturerId);

            return $user;
        });

        return $this->getUser($user->fresh());
    }

    public function updateUser(User $user, UserFormData $data): UserData
    {
        $this->db->transaction(function () use ($user, $data) {
            $user->fill([
                'name' => $data->name,
                'email' => $data->email,
                'faculty_id' => $data->facultyId,
                'study_program_id' => $data->studyProgramId,
            ]);

            if ($data->password !== null) {
                $user->password = $data->password;
            }

            $user->save();
            $user->syncRoles([$data->role->value]);
            $this->syncLecturer($user, $data->lecturerId);
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
            $user->update(['is_active' => $isActive]);

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
