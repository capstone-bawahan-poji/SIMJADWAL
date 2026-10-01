<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Account\Auth\ForgotPasswordRequest;
use App\Http\Requests\Account\Auth\IssueTokenRequest;
use App\Http\Requests\Account\Auth\ResetPasswordRequest;
use App\Services\Account\PasswordService;
use App\Services\Account\TokenAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends ApiController
{
    public function __construct(
        private readonly Request $request,
        private readonly TokenAuthService $tokenAuthService,
        private readonly PasswordService $passwordService,
    ) {}

    public function login(IssueTokenRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->response($this->tokenAuthService->issueToken(
            email: $v['email'],
            password: $v['password'],
            deviceName: $v['device_name'],
        ));
    }

    public function logout(): Response
    {
        $this->tokenAuthService->revokeCurrentToken($this->request->user());

        return $this->noContent();
    }

    public function me(): JsonResponse
    {
        return $this->response($this->tokenAuthService->me($this->request->user()));
    }

    public function forgotPassword(ForgotPasswordRequest $request): Response
    {
        $this->passwordService->sendResetLink($request->validated('email'));

        return $this->noContent(Response::HTTP_ACCEPTED);
    }

    public function resetPassword(ResetPasswordRequest $request): Response
    {
        $v = $request->validated();

        $this->passwordService->resetPassword(
            email: $v['email'],
            token: $v['token'],
            password: $v['password'],
        );

        return $this->noContent();
    }
}
