<?php

namespace Tests\Feature\Auth;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The registration flag is evaluated while the application boots, because
 * Fortify decides which routes exist at that point. Changing config() in the
 * middle of a test cannot remove routes that are already registered, so the
 * flag is applied right after configuration loads and before service
 * providers register.
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->afterBootstrapping(
            LoadConfiguration::class,
            fn ($app) => $app['config']->set('features.registration', false),
        );

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

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
