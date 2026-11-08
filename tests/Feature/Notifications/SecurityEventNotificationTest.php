<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Support\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;
use Tests\Concerns\SecurityEventNotificationHelpers;
use Tests\TestCase;

/**
 * Specifies when a security notification email is sent.
 *
 * - Sent exactly once, and only after the operation actually succeeded.
 * - Never sent when the operation is rejected (wrong password, invalid code, ...).
 * - Delivery problems never fail or roll back the operation itself.
 * - The mail never carries secrets, tokens, links or the new email address.
 */
class SecurityEventNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SecurityEventNotificationHelpers;

    // Password change

    public function test_password_change_notifies_the_user_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->updatePassword($user, 'password')->assertSessionHasNoErrors();

        $this->assertNotifiedOnce($user, SecurityEvent::PasswordChanged);
    }

    public function test_password_change_with_wrong_current_password_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->updatePassword($user, 'wrong-password')->assertSessionHasErrors('current_password');

        Notification::assertNothingSent();
    }

    public function test_password_change_succeeds_even_when_notification_fails(): void
    {
        $user = User::factory()->create();
        $attempts = $this->failNotificationDelivery();

        $this->updatePassword($user, 'password')->assertSessionHasNoErrors();

        $this->assertDeliveryFailedWithoutAffectingOperation($attempts);
        $this->assertTrue(password_verify('new-password', $user->fresh()->password));
    }

    public function test_password_change_with_cancelled_save_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $originalPassword = $user->password;
        User::saving(fn () => false);

        $this->updatePassword($user, 'password')
            ->assertSessionHasErrors('security')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('Password could not be updated.')]);

        $this->assertSame($originalPassword, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    // Password reset

    public function test_password_reset_notifies_the_user_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->resetPassword($user, Password::createToken($user))->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('new-password', $user->fresh()->password));
        $this->assertNotifiedOnce($user, SecurityEvent::PasswordReset);
    }

    public function test_password_reset_with_invalid_token_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->resetPassword($user, 'invalid-token')->assertSessionHasErrors('email');

        $this->assertFalse(password_verify('new-password', $user->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_password_reset_with_invalid_password_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        Notification::assertNothingSent();
    }

    public function test_password_reset_with_cancelled_save_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $token = Password::createToken($user);
        User::saving(fn () => false);

        $this->resetPassword($user, $token);

        $this->assertSame($originalPassword, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_requesting_a_password_reset_link_does_not_notify_as_a_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertNotSentTo($user, GeneralNotification::class);
    }

    // Two-factor authentication

    public function test_two_factor_with_confirmation_notifies_only_once_confirmed(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));
        Notification::assertNothingSent();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors();
        Notification::assertNothingSent();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $this->currentTwoFactorCode($user)])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);
    }

    public function test_two_factor_confirmation_that_is_not_saved_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));
        $code = $this->currentTwoFactorCode($user);

        $confirmedEvents = 0;
        Event::listen(TwoFactorAuthenticationConfirmed::class, function () use (&$confirmedEvents): void {
            $confirmedEvents++;
        });

        // Fortify fires its event even when the model save was cancelled.
        User::saving(fn () => false);

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $code]);

        $this->assertSame(1, $confirmedEvents, 'Fortify must have fired the confirmed event.');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        Notification::assertNothingSent();
    }

    public function test_two_factor_without_confirmation_notifies_once_on_enable(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => false, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);
    }

    public function test_disabling_two_factor_enabled_without_confirmation_notifies_the_user_once(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => false, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('two-factor.disable'))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->two_factor_secret);
        Notification::assertCount(2);
        Notification::assertSentTo(
            $user,
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels) => $notification->title === SecurityEvent::TwoFactorDisabled->subject()
                && $notification->body === SecurityEvent::TwoFactorDisabled->mailBody()
                && $channels === ['mail'],
        );
    }

    public function test_disabling_two_factor_notifies_the_user_once(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));
        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $this->currentTwoFactorCode($user)]);
        Notification::fake();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('two-factor.disable'));

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorDisabled);
    }

    public function test_disabling_two_factor_that_was_never_observed_being_enabled_still_notifies(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        // Seeded directly, as if this account had 2FA before this provider's
        // transition tracking existed, or its record of that transition was
        // lost. Unlike tracking kept outside the row itself, reading
        // two_factor_confirmed_at's own before/after state needs no prior
        // observation of the enable to get the disable right.
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('two-factor.disable'));

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorDisabled);
    }

    public function test_disabling_two_factor_that_was_never_enabled_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('two-factor.disable'));

        Notification::assertNothingSent();
    }

    public function test_disabling_an_unconfirmed_two_factor_setup_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        Notification::assertNothingSent();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('two-factor.disable'))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->two_factor_secret);
        Notification::assertNothingSent();
    }

    public function test_reconfirming_two_factor_does_not_notify_again(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $this->currentTwoFactorCode($user)])
            ->assertSessionHasNoErrors();

        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);

        // A still-valid code resubmitted against the already-confirmed
        // account must not notify a second time. Only the replay-protection
        // entry for this one code is cleared (not the whole cache store),
        // since this provider's own active/inactive record also lives in the
        // cache and must survive for this assertion to mean anything.
        $code = $this->currentTwoFactorCode($user);
        Cache::forget('fortify.2fa_codes.'.md5($code));

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();

        Notification::assertCount(1);
    }

    public function test_reconfirming_two_factor_from_a_separate_request_does_not_notify_again(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));
        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $this->currentTwoFactorCode($user)])
            ->assertSessionHasNoErrors();

        $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);

        // A fresh User instance (as a separate HTTP request would resolve,
        // unlike $user above, which this test keeps reusing) resubmitting a
        // still-valid code must not notify again either, even if Eloquent's
        // "updating" event wouldn't fire for this particular resubmit.
        $freshUser = $user->fresh();
        $this->assertNotNull($freshUser);
        $code = $this->currentTwoFactorCode($freshUser);
        Cache::forget('fortify.2fa_codes.'.md5($code));

        $this->actingAs($freshUser)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();

        Notification::assertCount(1);
    }

    public function test_regenerating_recovery_codes_notifies_the_user_once(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Notification::fake();
        $user = User::factory()->withTwoFactor()->create();
        $previousCodes = $user->two_factor_recovery_codes;

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.regenerate-recovery-codes'));

        $this->assertNotSame($previousCodes, $user->fresh()->two_factor_recovery_codes);
        $this->assertNotifiedOnce($user, SecurityEvent::RecoveryCodesRegenerated);
    }

    public function test_regenerating_recovery_codes_with_cancelled_save_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Notification::fake();
        $user = User::factory()->withTwoFactor()->create();
        $previousCodes = $user->two_factor_recovery_codes;
        User::saving(fn () => false);

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->post(route('two-factor.regenerate-recovery-codes'));

        $this->assertSame($previousCodes, $user->fresh()->two_factor_recovery_codes);
        Notification::assertNothingSent();
    }

    // Passkeys

    public function test_deleting_a_passkey_notifies_the_user_once(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        Notification::fake();
        $user = User::factory()->create();
        $passkey = $this->createPasskey($user);

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('passkey.destroy', $passkey));

        $this->assertModelMissing($passkey);
        $this->assertNotifiedOnce($user, SecurityEvent::PasskeyRemoved);
    }

    public function test_deleting_another_users_passkey_is_forbidden_and_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        Notification::fake();
        $user = User::factory()->create();
        $passkey = $this->createPasskey(User::factory()->create());

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->delete(route('passkey.destroy', $passkey))
            ->assertForbidden();

        $this->assertModelExists($passkey);
        Notification::assertNothingSent();
    }

    public function test_registering_an_invalid_passkey_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->postJson(route('passkey.store'), [
                'name' => 'Laptop',
                'credential' => [
                    'id' => 'invalid',
                    'rawId' => 'invalid',
                    'type' => 'public-key',
                    'response' => ['clientDataJSON' => 'invalid', 'attestationObject' => 'invalid'],
                ],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $user->passkeys()->count());
        Notification::assertNothingSent();
    }

    /**
     * A valid WebAuthn attestation cannot be forged in a test, so the package's
     * "registered" event is fired directly; what matters is whether the passkey
     * it carries was actually stored.
     */
    public function test_a_stored_passkey_registration_notifies_the_user_once(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        Notification::fake();
        $user = User::factory()->create();

        event(new PasskeyRegistered($user, $this->createPasskey($user)));
        $this->runDeferredCallbacks();

        $this->assertNotifiedOnce($user, SecurityEvent::PasskeyAdded);
    }

    public function test_a_passkey_registration_event_for_an_unstored_passkey_does_not_notify(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());
        Notification::fake();
        $user = User::factory()->create();

        event(new PasskeyRegistered($user, new Passkey));
        $this->runDeferredCallbacks();

        Notification::assertNothingSent();
    }

    // Email change

    public function test_email_change_notifies_only_the_old_address_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->changeEmail($user, 'new@example.com', 'password')->assertSessionHasNoErrors();

        $this->assertSame('new@example.com', $user->fresh()->email);
        Notification::assertCount(1);
        Notification::assertSentOnDemand(
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notification->title === SecurityEvent::EmailChanged->subject()
                && $notification->body === SecurityEvent::EmailChanged->mailBody()
                && $channels === ['mail']
                && $notifiable->routes === ['mail' => 'old@example.com'],
        );
        Notification::assertNotSentTo($user, GeneralNotification::class);
    }

    public function test_email_change_with_wrong_password_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->changeEmail($user, 'new@example.com', 'wrong-password')->assertSessionHasErrors('current_password');

        $this->assertSame('old@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_email_change_to_a_taken_address_does_not_notify(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->changeEmail($user, 'taken@example.com', 'password')->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_name_only_change_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'old@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_email_change_succeeds_even_when_notification_fails(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $attempts = $this->failNotificationDelivery();

        $this->changeEmail($user, 'new@example.com', 'password')->assertSessionHasNoErrors();

        $this->assertDeliveryFailedWithoutAffectingOperation($attempts);
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_email_change_with_cancelled_save_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        User::saving(fn () => false);

        $this->changeEmail($user, 'new@example.com', 'password')
            ->assertSessionHasErrors('profile')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('Profile could not be updated.')]);

        $this->assertSame('old@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    // Account deletion

    public function test_account_deletion_notifies_the_deleted_address_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'gone@example.com']);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertModelMissing($user);
        Notification::assertCount(1);
        Notification::assertSentOnDemand(
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notification->title === SecurityEvent::AccountDeleted->subject()
                && $notification->body === SecurityEvent::AccountDeleted->mailBody()
                && $channels === ['mail']
                && $notifiable->routes === ['mail' => 'gone@example.com'],
        );
    }

    public function test_account_deletion_with_wrong_password_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($user);
        Notification::assertNothingSent();
    }

    public function test_account_deletion_succeeds_even_when_notification_fails(): void
    {
        $user = User::factory()->create();
        $attempts = $this->failNotificationDelivery();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDeliveryFailedWithoutAffectingOperation($attempts);
        $this->assertModelMissing($user);
    }

    public function test_account_deletion_with_cancelled_delete_does_not_notify(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        User::deleting(fn () => false);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('account')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('Your account could not be deleted.')]);

        $this->assertModelExists($user);
        Notification::assertNothingSent();
    }

    // Mail content

    public function test_no_notification_mail_contains_links_tokens_or_the_new_address(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        foreach (SecurityEvent::cases() as $event) {
            $mail = (new GeneralNotification($event->subject(), $event->mailBody()))->toMail($user);
            $rendered = strtolower($mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines));

            $this->assertNotEmpty($mail->subject, $event->name);
            $this->assertNull($mail->actionUrl, $event->name);
            $this->assertStringNotContainsString('http', $rendered, $event->name);
            $this->assertStringNotContainsString('token', $rendered, $event->name);
            $this->assertStringNotContainsString('@', $rendered, $event->name);
        }
    }

    // Helpers
}
