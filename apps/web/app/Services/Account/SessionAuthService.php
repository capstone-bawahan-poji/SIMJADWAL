<?php

namespace App\Services\Account;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session authentication for the web app (Web\Auth).
 * Token login for the mobile and desktop clients lives in TokenAuthService.
 */
readonly class SessionAuthService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
        private Dispatcher $events,
        private Request $request,
    ) {}

    /**
     * Wrong password and inactive account give the same error, so accounts cannot be enumerated.
     *
     * @throws ValidationException when the credentials are wrong or the email + IP pair is locked out
     */
    public function login(string $email, string $password, bool $remember): void
    {
        $key = $this->throttleKey($email);

        $this->ensureIsNotRateLimited($key);

        if (! $this->guard()->attempt(['email' => $email, 'password' => $password, 'is_active' => true], $remember)) {
            $this->limiter->hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->limiter->clear($key);

        // New session id after login blocks session fixation.
        $this->request->session()->regenerate();
    }

    public function logout(): void
    {
        $this->guard()->logout();

        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }

    private function ensureIsNotRateLimited(string $key): void
    {
        if (! $this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $this->events->dispatch(new Lockout($this->request));

        $seconds = $this->limiter->availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    /**
     * Same key format as the `login` rate limiter in AppServiceProvider, used by /api/v1/auth/login.
     */
    private function throttleKey(string $email): string
    {
        return Str::transliterate(Str::lower($email).'|'.$this->request->ip());
    }

    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return $this->auth->guard('web');
    }
}
