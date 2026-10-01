<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\InertiaController;
use App\Http\Requests\Account\Auth\ResetPasswordRequest;
use App\Services\Account\PasswordService;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends InertiaController
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword/Index', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $v = $request->validated();

        $this->passwordService->resetPassword(
            email: $v['email'],
            token: $v['token'],
            password: $v['password'],
        );

        return to_route('login')->with('status', __(PasswordBroker::PASSWORD_RESET));
    }
}
