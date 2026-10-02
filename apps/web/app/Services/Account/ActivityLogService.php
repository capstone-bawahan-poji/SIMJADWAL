<?php

namespace App\Services\Account;

use App\Data\Account\ActivityLogData;
use App\Models\Account\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audit trail of account changes. The causer is the signed-in user (session or token).
 * Passwords are never stored: a password change is recorded as password_changed only.
 */
readonly class ActivityLogService
{
    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_STATUS_CHANGED = 'user.status_changed';

    private const LOG_NAME = 'account';

    public function __construct(private ActivityLog $activityLog) {}

    /**
     * @return LengthAwarePaginator<int, ActivityLogData>
     */
    public function getActivityLogs(?string $event, ?int $causerId, ?int $userId, int $perPage): LengthAwarePaginator
    {
        return ActivityLogData::prepareQuery($this->activityLog->newQuery())
            ->when($event, fn (Builder $query) => $query->where('event', $event))
            ->when($causerId, fn (Builder $query) => $query->where('causer_type', User::class)->where('causer_id', $causerId))
            ->when($userId, fn (Builder $query) => $query->where('subject_type', User::class)->where('subject_id', $userId))
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (ActivityLog $activityLog) => ActivityLogData::from($activityLog));
    }

    /**
     * Call inside a transaction, after the account and its role are saved.
     */
    public function userCreated(User $user): void
    {
        activity(self::LOG_NAME)
            ->performedOn($user)
            ->event(self::USER_CREATED)
            ->withChanges(['attributes' => $this->snapshot($user)])
            ->log(__('Created account :email', ['email' => $user->email]));
    }

    /**
     * @param  array<string, mixed>  $before  snapshot() taken before the change
     */
    public function userUpdated(User $user, array $before, bool $passwordChanged): void
    {
        $after = $this->snapshot($user);
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        if ($changed === [] && ! $passwordChanged) {
            return;
        }

        activity(self::LOG_NAME)
            ->performedOn($user)
            ->event(self::USER_UPDATED)
            ->withChanges([
                'old' => array_intersect_key($before, array_flip($changed)),
                'attributes' => array_intersect_key($after, array_flip($changed)),
            ])
            ->withProperties(['password_changed' => $passwordChanged])
            ->log(__('Updated account :email', ['email' => $user->email]));
    }

    public function userStatusChanged(User $user, bool $wasActive): void
    {
        if ($wasActive === $user->is_active) {
            return;
        }

        activity(self::LOG_NAME)
            ->performedOn($user)
            ->event(self::USER_STATUS_CHANGED)
            ->withChanges(['old' => ['is_active' => $wasActive], 'attributes' => ['is_active' => $user->is_active]])
            ->log($user->is_active
                ? __('Activated account :email', ['email' => $user->email])
                : __('Deactivated account :email', ['email' => $user->email]));
    }

    /**
     * Audited fields of an account. identity_number is the stored value, not the lecturer NIP.
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        $user->unsetRelation('roles')->unsetRelation('lecturer');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'identity_number' => $user->getRawOriginal('identity_number'),
            'role' => $user->roles->first()?->name,
            'faculty_id' => $user->faculty_id,
            'study_program_id' => $user->study_program_id,
            'lecturer_id' => $user->lecturer_id,
            'is_active' => $user->is_active,
        ];
    }
}
