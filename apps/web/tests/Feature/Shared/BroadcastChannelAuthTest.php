<?php

namespace Tests\Feature\Shared;

use App\Events\Shared\ResourcesChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * private-changes is open to every active signed-in user: by session on the web, by token on the desktop.
 */
class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests broadcast to the null driver. Channel auth needs a real Pusher-protocol driver to sign
        // the reply, which works offline. routes/channels.php is loaded again onto that driver.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        Broadcast::purge();
        require base_path('routes/channels.php');
        // Keep writes made by these requests from reaching a real Reverb server.
        Event::fake([ResourcesChanged::class]);
    }

    private function payload(): array
    {
        return ['socket_id' => '1234.5678', 'channel_name' => 'private-changes'];
    }

    public function test_web_session_is_authorized(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/broadcasting/auth', $this->payload())
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_desktop_token_is_authorized(): void
    {
        $token = User::factory()->create()->createToken('desktop')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/broadcasting/auth', $this->payload())
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_guest_is_rejected(): void
    {
        $this->postJson('/api/v1/broadcasting/auth', $this->payload())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->post('/broadcasting/auth', $this->payload())->assertForbidden();
    }

    public function test_inactive_user_is_rejected(): void
    {
        $token = User::factory()->inactive()->create()->createToken('desktop')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/broadcasting/auth', $this->payload())->assertUnauthorized();
    }

    public function test_unknown_channel_is_rejected(): void
    {
        $token = User::factory()->create()->createToken('desktop')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-secret'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
