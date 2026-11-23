<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BootsWithFeaturesDisabled;
use Tests\TestCase;

class PasskeysDisabledTest extends TestCase
{
    use BootsWithFeaturesDisabled, RefreshDatabase;

    protected array $disabledFeatures = ['passkeys'];

    public function test_passkey_routes_do_not_exist_when_disabled(): void
    {
        foreach ([
            'passkey.login',
            'passkey.login-options',
            'passkey.confirm',
            'passkey.confirm-options',
            'passkey.store',
            'passkey.registration-options',
            'passkey.destroy',
            'well-known.passkeys',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] should not exist.");
        }
    }

    public function test_passkey_endpoints_return_not_found_when_disabled(): void
    {
        $this->get('/.well-known/passkey-endpoints')->assertNotFound();
        $this->get('/passkeys/login/options')->assertNotFound();
        $this->post('/passkeys/login')->assertNotFound();
    }

    public function test_login_page_reports_passkeys_as_disabled(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('features.passkeys', false));
    }

    public function test_security_page_does_not_offer_passkey_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('canManagePasskeys', false)
                ->where('passkeys', []));
    }

    public function test_password_login_still_works_when_passkeys_are_disabled(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
