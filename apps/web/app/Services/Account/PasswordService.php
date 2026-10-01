<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

/**
 * Password flows shared by the web session (Web\Auth) and the token API (Api\V1).
 */
readonly class PasswordService
{
    public function __construct(
        private PasswordBrokerManager $passwords,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    /**
     * Always succeeds from the caller's view, even for unknown emails.
     */
    public function sendResetLink(string $email): void
    {
        $this->broker()->sendResetLink(['email' => $email]);
    }

    /**
     * Revokes every API token, so a stolen token stops working after the reset.
     *
     * @throws ValidationException when the token is invalid or expired
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $status = $this->broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password) {
                $this->db->transaction(function () use ($user, $password) {
                    $user->forceFill(['password' => $password])->setRememberToken(null);
                    $user->save();
                    $user->tokens()->delete();
                });

                $this->events->dispatch(new PasswordReset($user));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    private function broker(): PasswordBroker
    {
        return $this->passwords->broker();
    }
}
