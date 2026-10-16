<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('http://127.0.0.1:5173');
    }

    public function test_frontend_routes_redirect_to_the_react_application(): void
    {
        $this->get('/login')->assertRedirect('http://127.0.0.1:5173/login');
        $this->get('/projetos?status=ativo')
            ->assertRedirect('http://127.0.0.1:5173/projetos?status=ativo');
    }
}
