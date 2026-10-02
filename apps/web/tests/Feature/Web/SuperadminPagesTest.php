<?php

namespace Tests\Feature\Web;

use App\Models\MasterData\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class SuperadminPagesTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PAGES = ['/superadmin/accounts', '/superadmin/faculty-program', '/superadmin/rooms', '/superadmin/slots'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_superadmin_opens_every_superadmin_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach (self::PAGES as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_other_roles_are_refused(): void
    {
        foreach ([User::factory()->student()->create(), User::factory()->facultyAdmin()->create()] as $user) {
            $this->actingAs($user);

            foreach (self::PAGES as $page) {
                $this->get($page)->assertForbidden();
            }
        }
    }

    public function test_dashboard_gives_counts_to_superadmin_only(): void
    {
        [$program] = StudyProgram::factory()->count(2)->create();
        User::factory()->student($program)->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.faculties', 2)
            ->where('stats.study_programs', 2)
            ->where('stats.users.total', 2)
            ->where('stats.users.by_role.mahasiswa', 1)
            ->where('stats.users.by_role.superadmin', 1));

        $this->actingAs(User::factory()->student()->create());
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats', null));
    }

    public function test_expired_csrf_token_on_internal_api_returns_session_expired(): void
    {
        Route::post('/api/internal/_csrf-probe', fn () => throw new TokenMismatchException);

        $this->postJson('/api/internal/_csrf-probe')
            ->assertStatus(419)
            ->assertExactJson(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.', 'code' => 'SESSION_EXPIRED']);
    }
}
