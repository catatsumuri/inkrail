<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BootsWithFeaturesDisabled;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use BootsWithFeaturesDisabled, RefreshDatabase;

    protected array $disabledFeatures = ['registration'];

    public function test_registration_routes_do_not_exist_when_disabled(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));
    }

    public function test_registration_endpoints_return_not_found_when_disabled(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_login_page_reports_registration_as_disabled(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/login')
                ->where('features.registration', false));

        $this->get('/')
            ->assertInertia(fn ($page) => $page->where('features.registration', false));
    }
}
