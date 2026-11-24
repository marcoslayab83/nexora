<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_and_login_renders_nexora(): void
    {
        $this->get('/')->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertSee('NEXORA')
            ->assertSee('Aquí empieza el futuro de tu negocio');
    }
}
