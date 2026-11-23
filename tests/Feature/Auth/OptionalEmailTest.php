<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class OptionalEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'username' => 'test.user',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }

    // Registration

    public function test_an_account_can_register_without_an_email_address(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), $this->payload())->assertSessionHasNoErrors();

        $user = User::sole();

        $this->assertNull($user->email);
        $this->assertSame('test.user', $user->username);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_blank_email_is_stored_as_null_and_many_accounts_may_have_none(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), $this->payload(['username' => 'first', 'email' => '']))
            ->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->post(route('register.store'), $this->payload(['username' => 'second', 'email' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, User::whereNull('email')->count());
    }

    public function test_registering_without_an_email_sends_no_verification_mail(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());
        Notification::fake();

        $this->post(route('register.store'), $this->payload());

        Notification::assertNothingSent();
    }

    public function test_registering_with_an_email_still_sends_a_verification_mail(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());
        $this->skipUnlessFortifyHas(Features::emailVerification());
        Notification::fake();

        $this->post(route('register.store'), $this->payload(['email' => 'new@example.com']));

        Notification::assertSentTo(User::sole(), VerifyEmail::class);
    }

    public function test_a_supplied_email_is_still_validated(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), $this->payload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
        $this->post(route('register.store'), $this->payload(['email' => 'Taken@Example.com']))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // Verification

    public function test_an_account_without_an_email_can_use_pages_that_require_verification(): void
    {
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('appearance.edit'))->assertOk();
    }

    public function test_an_account_with_an_unverified_email_is_still_stopped(): void
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('articles.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_an_account_without_an_email_is_never_a_security_log_viewer(): void
    {
        config(['audit.security_viewers' => ['viewer@example.com']]);

        $this->actingAs(User::factory()->withoutEmail('alice')->create())
            ->getJson(route('admin.security-events.index'))
            ->assertForbidden();
    }

    // Signing in

    public function test_an_account_without_an_email_signs_in_with_its_username(): void
    {
        $user = User::factory()->withoutEmail('alice')->create();

        $this->post(route('login.store'), ['login' => 'alice', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_without_an_email_still_gets_the_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->withoutEmail('alice')->withTwoFactor()->create();

        $this->post(route('login.store'), ['login' => 'alice', 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertSame($user->id, session('login.id'));
    }

    public function test_password_confirmation_works_for_an_account_without_an_email(): void
    {
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    }

    public function test_the_login_identifier_and_passkey_username_fall_back_to_the_username(): void
    {
        $withoutEmail = User::factory()->withoutEmail('alice')->create();
        $withBoth = User::factory()->withUsername('bob')->create(['email' => 'bob@example.com']);

        $this->assertSame('alice', $withoutEmail->login);
        $this->assertSame('alice', $withoutEmail->getPasskeyUsername());
        $this->assertSame('bob@example.com', $withBoth->login);
        $this->assertSame('bob', $withBoth->getPasskeyUsername());
    }

    public function test_the_shared_user_exposes_a_null_email(): void
    {
        $this->actingAs(User::factory()->withoutEmail('alice')->create())
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.email', null)
                ->where('auth.user.username', 'alice'));
    }
}
