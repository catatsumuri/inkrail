<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BootsWithFeaturesDisabled;
use Tests\TestCase;

class PasswordConfirmationDisabledTest extends TestCase
{
    use BootsWithFeaturesDisabled, RefreshDatabase;

    protected array $enabledFeatures = ['two_factor', 'passkeys'];

    protected array $disabledFeatures = ['password_confirmation'];

    public function test_session_management_routes_do_not_exist_when_disabled(): void
    {
        foreach (['sessions.index', 'sessions.destroy', 'sessions.destroy-others', 'sessions.destroy-all'] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] should not exist.");
        }

        $this->actingAs(User::factory()->create())
            ->get('/settings/sessions')
            ->assertNotFound();
    }

    public function test_security_page_does_not_require_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/security'));
    }

    public function test_two_factor_setup_does_not_require_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('two-factor.enable'))
            ->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_passkey_management_does_not_require_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('passkey.registration-options'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge']])
            ->assertSessionHas('passkey.registration_options');
    }

    public function test_pages_report_password_confirmation_as_disabled(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('features.password_confirmation', false));
    }
}
