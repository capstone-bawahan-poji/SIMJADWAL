<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private const PRODI_PAGES = [
        '/prodi/courses', '/prodi/lecturers', '/prodi/mappings',
        '/prodi/constraints', '/prodi/constraints/review', '/prodi/schedule-matrix',
    ];

    private const FACULTY_PAGES = ['/fakultas/constraints', '/fakultas/ga', '/fakultas/rooms'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_study_program_admin_opens_prodi_pages_only(): void
    {
        $this->actingAs(User::factory()->studyProgramAdmin()->create());

        foreach (self::PRODI_PAGES as $page) {
            $this->get($page)->assertOk();
        }

        foreach (self::FACULTY_PAGES as $page) {
            $this->get($page)->assertForbidden();
        }
    }

    public function test_faculty_admin_opens_faculty_pages_only(): void
    {
        $this->actingAs(User::factory()->facultyAdmin()->create());

        foreach (self::FACULTY_PAGES as $page) {
            $this->get($page)->assertOk();
        }

        foreach (self::PRODI_PAGES as $page) {
            $this->get($page)->assertForbidden();
        }
    }

    public function test_other_roles_are_refused(): void
    {
        $this->actingAs(User::factory()->student()->create());

        foreach ([...self::PRODI_PAGES, ...self::FACULTY_PAGES] as $page) {
            $this->get($page)->assertForbidden();
        }
    }

    public function test_dashboard_renders_a_page_per_role(): void
    {
        $this->actingAs(User::factory()->studyProgramAdmin()->create());
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Prodi/Dashboard'));

        $this->actingAs(User::factory()->facultyAdmin()->create());
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('Fakultas/Dashboard'));
    }

    public function test_sample_data_label_is_shared_in_indonesian(): void
    {
        $this->actingAs(User::factory()->studyProgramAdmin()->create());

        $this->get('/prodi/constraints')->assertInertia(fn (Assert $page) => $page->where('labels.sample_data', 'Data contoh'));
    }
}
