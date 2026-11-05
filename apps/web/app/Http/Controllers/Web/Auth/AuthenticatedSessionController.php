<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\InertiaController;
use App\Http\Requests\Account\Auth\LoginRequest;
use App\Models\User;
use App\Services\Account\SessionAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticatedSessionController extends InertiaController
{
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login/Index', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $v = $request->validated();

        $this->sessionAuthService->login(
            email: $v['email'],
            password: $v['password'],
            remember: $request->boolean('remember'),
        );

        $intended = $request->session()->pull('url.intended');

        return redirect($intended !== null && $this->roleMayOpen($request->user(), $intended)
            ? $intended
            : route('dashboard', absolute: false));
    }

    /**
     * The intended URL may belong to another role (a page left open before logging in as
     * someone else). Follow it only when the route's role middleware lets this user in.
     */
    private function roleMayOpen(User $user, string $url): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($url));
        } catch (HttpException) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')
                && ! $user->hasAnyRole(explode('|', substr($middleware, 5)))) {
                return false;
            }
        }

        return true;
    }

    public function destroy(): RedirectResponse
    {
        $this->sessionAuthService->logout();

        return redirect('/');
    }
}
