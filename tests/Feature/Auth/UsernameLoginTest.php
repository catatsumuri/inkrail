<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_log_in_with_their_username(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->post(route('login.store'), ['login' => 'alice', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_still_log_in_with_their_email_address(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_ignores_letter_case(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->post(route('login.store'), ['login' => 'ALICE', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_accounts_without_a_username_log_in_with_their_email_address(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->username);

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_old_email_field_is_no_longer_the_login_field(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_a_wrong_password_for_a_username_is_recorded_against_the_account(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->post(route('login.store'), ['login' => 'alice', 'password' => 'wrong-password']);

        $event = Activity::inLog('security')->sole();

        $this->assertSame('login.failed', $event->description);
        $this->assertTrue($event->subject->is($user));
        $this->assertStringNotContainsString('alice', json_encode($event->properties));
        $this->assertStringNotContainsString('wrong-password', json_encode($event->properties));
    }

    public function test_an_unknown_username_and_a_wrong_password_look_the_same(): void
    {
        User::factory()->withUsername('alice')->create();

        $unknown = $this->post(route('login.store'), ['login' => 'nobody', 'password' => 'wrong-password']);
        $unknown->assertSessionHasErrors('login');
        $unknownMessage = $unknown->baseResponse->getSession()->get('errors')->getBag('default')->first('login');

        $known = $this->post(route('login.store'), ['login' => 'alice', 'password' => 'wrong-password']);
        $known->assertSessionHasErrors('login');
        $knownMessage = $known->baseResponse->getSession()->get('errors')->getBag('default')->first('login');

        $this->assertNotSame('', $unknownMessage);
        $this->assertSame($unknownMessage, $knownMessage);
    }

    public function test_failed_attempts_are_throttled_per_login_value(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['login' => 'alice', 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['login' => 'alice', 'password' => 'password']);

        $this->assertGuest();

        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_signing_in_with_a_username_still_requires_the_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->withUsername('alice')->create();

        $this->post(route('login.store'), ['login' => 'alice', 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_password_confirmation_still_works_for_users_with_a_username(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    }
}
