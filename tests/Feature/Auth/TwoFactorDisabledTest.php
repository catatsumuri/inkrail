<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BootsWithFeaturesDisabled;
use Tests\TestCase;

class TwoFactorDisabledTest extends TestCase
{
    use BootsWithFeaturesDisabled, RefreshDatabase;

    protected array $disabledFeatures = ['two_factor'];

    public function test_two_factor_routes_do_not_exist_when_disabled(): void
    {
        foreach ([
            'two-factor.login',
            'two-factor.login.store',
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.disable',
            'two-factor.qr-code',
            'two-factor.secret-key',
            'two-factor.recovery-codes',
            'two-factor.regenerate-recovery-codes',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] should not exist.");
        }
    }

    public function test_two_factor_endpoints_return_not_found_when_disabled(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();
        $this->post('/two-factor-challenge')->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->post('/user/two-factor-authentication')
            ->assertNotFound();
    }

    public function test_security_page_does_not_offer_two_factor_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'));
    }

    public function test_password_login_still_works_when_two_factor_is_disabled(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Documents the security trade-off of the flag: with it off, Fortify no
     * longer redirects to the challenge, so an enrolled account signs in with
     * its password alone.
     */
    public function test_enrolled_accounts_lose_the_login_challenge_when_disabled(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
