<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Web\Controller;
use App\Http\Requests\Account\Profile\UpdatePasswordRequest;
use App\Services\Account\PasswordService;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {}

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->passwordService->updatePassword($request->user(), $request->validated('password'));

        return back();
    }
}
