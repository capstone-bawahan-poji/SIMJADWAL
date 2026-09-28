<?php

namespace App\Services\Account;

use App\Data\Account\AuthTokenData;
use App\Data\Account\UserData;
use App\Exceptions\Shared\ApiException;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token authentication for the mobile and desktop clients (/api/v1/auth).
 * Password reset lives in PasswordService, shared with the web session flow.
 */
readonly class TokenAuthService
{
    public function __construct(
        private User $user,
        private Hasher $hasher,
    ) {}

    /**
     * Wrong email, wrong password and inactive account give the same error, so accounts cannot be enumerated.
     */
    public function issueToken(string $email, string $password, string $deviceName): AuthTokenData
    {
        $user = $this->user->newQuery()->where('email', $email)->first();

        if (! $user || ! $user->is_active || ! $this->hasher->check($password, $user->password)) {
            throw ApiException::invalidCredentials();
        }

        return new AuthTokenData(
            token: $user->createToken($deviceName)->plainTextToken,
            user: UserData::from(UserData::loadRelations($user)),
        );
    }

    public function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function me(User $user): UserData
    {
        return UserData::from(UserData::loadRelations($user));
    }
}
