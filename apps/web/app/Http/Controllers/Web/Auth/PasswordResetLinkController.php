<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\InertiaController;
use App\Http\Requests\Account\Auth\ForgotPasswordRequest;
use App\Services\Account\PasswordService;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends InertiaController
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword/Index', [
            'status' => session('status'),
        ]);
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwordService->sendResetLink($request->validated('email'));

        return back()->with('status', __(PasswordBroker::RESET_LINK_SENT));
    }
}
