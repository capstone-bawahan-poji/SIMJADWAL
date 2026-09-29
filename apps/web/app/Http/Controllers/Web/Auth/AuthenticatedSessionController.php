<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\InertiaController;
use App\Http\Requests\Account\Auth\LoginRequest;
use App\Services\Account\SessionAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

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

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(): RedirectResponse
    {
        $this->sessionAuthService->logout();

        return redirect('/');
    }
}
