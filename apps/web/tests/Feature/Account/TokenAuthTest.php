<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class TokenAuthTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->studyProgramAdmin()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'desktop-tauri',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'admin_prodi')
            ->assertJsonPath('data.user.study_program_id', $user->study_program_id)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'role', 'is_active']]]);

        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * Wrong password and inactive account must look the same to the client (no account enumeration).
     */
    public function test_login_rejects_wrong_password_and_inactive_account_with_same_error(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        foreach ([[$user->email, 'wrong'], [$inactive->email, 'password']] as [$email, $password]) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'x'])
                ->assertUnauthorized()
                ->assertJsonPath('code', 'INVALID_CREDENTIALS');
        }
    }

    public function test_login_validation_error_uses_contract_shape(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email', 'password', 'device_name']]);
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_me_and_logout_with_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('android')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_forgot_password_is_accepted_for_unknown_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertStatus(202);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(202);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('android');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'new-password', 'device_name' => 'x'])
            ->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
    }
}
