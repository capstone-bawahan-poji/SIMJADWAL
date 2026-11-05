<?php

namespace App\Providers;

use App\Contracts\Account\ResolvesOrgScope;
use App\Enums\Account\Role;
use App\Models\User;
use App\Services\Account\RoleOrgScope;
use App\Services\Shared\ChangeRecorder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResolvesOrgScope::class, RoleOrgScope::class);
        $this->app->scoped(ChangeRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Password::defaults(fn () => Password::min(8));

        Gate::before(fn (User $user) => $user->hasRole(Role::SUPER_ADMIN->value) ? true : null);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()),
        ));

        $this->app->terminating(fn () => $this->app->make(ChangeRecorder::class)->flush());
        Queue::after(fn () => $this->app->make(ChangeRecorder::class)->flush());
    }
}