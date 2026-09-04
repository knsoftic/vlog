<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** The home page renders even on a completely empty (freshly migrated, unseeded) database. */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('No content has been published yet');
    }
}
