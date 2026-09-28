<?php

namespace App\Http\Controllers\Web\Account;

use App\Http\Controllers\Web\InertiaController;
use App\Http\Requests\Account\Profile\UpdateProfileRequest;
use App\Services\Account\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends InertiaController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $v = $request->validated();

        $this->userService->updateProfile($request->user(), name: $v['name'], email: $v['email']);

        return to_route('profile.edit');
    }
}
