<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Errors without a dedicated mapping still carry a code under api/*, so clients can switch on it.
 */
class ApiErrorFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('api/test/boom', fn () => throw new RuntimeException('database exploded'));
        Route::get('api/test/unavailable', fn () => abort(503));
        Route::get('test/boom', fn () => throw new RuntimeException('database exploded'));
    }

    public function test_unexpected_exception_returns_server_error_code_without_leaking_the_message(): void
    {
        config(['app.debug' => false]);

        $this->getJson('api/test/boom')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Terjadi kesalahan pada server.', 'code' => 'SERVER_ERROR']);
    }

    public function test_debug_mode_shows_the_real_message(): void
    {
        config(['app.debug' => true]);

        $this->getJson('api/test/boom')
            ->assertStatus(500)
            ->assertJsonPath('code', 'SERVER_ERROR')
            ->assertJsonPath('message', 'database exploded');
    }

    public function test_wrong_method_returns_method_not_allowed(): void
    {
        $this->postJson('/api/v1/auth/me')
            ->assertStatus(405)
            ->assertJsonPath('code', 'METHOD_NOT_ALLOWED')
            ->assertHeader('Allow');
    }

    public function test_other_http_errors_keep_their_status(): void
    {
        $this->getJson('api/test/unavailable')
            ->assertStatus(503)
            ->assertJsonPath('code', 'HTTP_ERROR');
    }

    public function test_web_routes_keep_the_default_renderer(): void
    {
        config(['app.debug' => false]);

        $this->get('test/boom')
            ->assertStatus(500)
            ->assertDontSee('SERVER_ERROR');
    }
}