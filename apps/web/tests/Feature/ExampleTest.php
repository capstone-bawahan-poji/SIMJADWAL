<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_shows_the_public_landing_page(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Public/Index'));
    }
}
