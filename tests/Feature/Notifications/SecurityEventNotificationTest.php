<?php

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

uses(RefreshDatabase::class);

uses(SecurityEventNotificationHelpers::class);

test('password change notifies the user once', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->updatePassword($user, 'password')->assertSessionHasNoErrors();

    $this->assertNotifiedOnce($user, SecurityEvent::PasswordChanged);
});

test('password change with wrong current password does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->updatePassword($user, 'wrong-password')->assertSessionHasErrors('current_password');

    Notification::assertNothingSent();
});

test('password change succeeds even when notification fails', function () {
    $user = User::factory()->create();
    $attempts = $this->failNotificationDelivery();

    $this->updatePassword($user, 'password')->assertSessionHasNoErrors();

    $this->assertDeliveryFailedWithoutAffectingOperation($attempts);
    expect(password_verify('new-password', $user->fresh()->password))->toBeTrue();
});

test('password change with cancelled save does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();
    $originalPassword = $user->password;
    User::saving(fn () => false);

    $this->updatePassword($user, 'password')
        ->assertSessionHasErrors('security')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('Password could not be updated.')]);

    expect($user->fresh()->password)->toBe($originalPassword);
    Notification::assertNothingSent();
});

test('password reset notifies the user once', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->resetPassword($user, Password::createToken($user))->assertSessionHasNoErrors();

    expect(password_verify('new-password', $user->fresh()->password))->toBeTrue();
    $this->assertNotifiedOnce($user, SecurityEvent::PasswordReset);
});

test('password reset with invalid token does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->resetPassword($user, 'invalid-token')->assertSessionHasErrors('email');

    expect(password_verify('new-password', $user->fresh()->password))->toBeFalse();
    Notification::assertNothingSent();
});

test('password reset with invalid password does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors('password');

    Notification::assertNothingSent();
});

test('password reset with cancelled save does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();
    $originalPassword = $user->password;
    $token = Password::createToken($user);
    User::saving(fn () => false);

    $this->resetPassword($user, $token);

    expect($user->fresh()->password)->toBe($originalPassword);
    Notification::assertNothingSent();
});

test('requesting a password reset link does not notify as a reset', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertNotSentTo($user, GeneralNotification::class);
});

test('two factor with confirmation notifies only once confirmed', function () {
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

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);
});

test('two factor confirmation that is not saved does not notify', function () {
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

    expect($confirmedEvents)->toBe(1, 'Fortify must have fired the confirmed event.');
    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
    Notification::assertNothingSent();
});

test('two factor without confirmation notifies once on enable', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    Features::twoFactorAuthentication(['confirm' => false, 'confirmPassword' => true]);
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->post(route('two-factor.enable'));

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
    $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorEnabled);
});

test('disabling two factor enabled without confirmation notifies the user once', function () {
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

    expect($user->fresh()->two_factor_secret)->toBeNull();
    Notification::assertCount(2);
    Notification::assertSentTo(
        $user,
        GeneralNotification::class,
        fn (GeneralNotification $notification, array $channels) => $notification->title === SecurityEvent::TwoFactorDisabled->subject()
            && $notification->body === SecurityEvent::TwoFactorDisabled->mailBody()
            && $channels === ['mail'],
    );
});

test('disabling two factor notifies the user once', function () {
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

    expect($user->fresh()->two_factor_secret)->toBeNull();
    $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorDisabled);
});

test('disabling two factor that was never observed being enabled still notifies', function () {
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

    expect($user->fresh()->two_factor_secret)->toBeNull();
    $this->assertNotifiedOnce($user, SecurityEvent::TwoFactorDisabled);
});

test('disabling two factor that was never enabled does not notify', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->delete(route('two-factor.disable'));

    Notification::assertNothingSent();
});

test('disabling an unconfirmed two factor setup does not notify', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->post(route('two-factor.enable'));

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
    Notification::assertNothingSent();

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->delete(route('two-factor.disable'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull();
    Notification::assertNothingSent();
});

test('reconfirming two factor does not notify again', function () {
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
});

test('reconfirming two factor from a separate request does not notify again', function () {
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
    expect($freshUser)->not->toBeNull();
    $code = $this->currentTwoFactorCode($freshUser);
    Cache::forget('fortify.2fa_codes.'.md5($code));

    $this->actingAs($freshUser)->withSession($this->passwordConfirmed())
        ->post(route('two-factor.confirm'), ['code' => $code])
        ->assertSessionHasNoErrors();

    Notification::assertCount(1);
});

test('regenerating recovery codes notifies the user once', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    Notification::fake();
    $user = User::factory()->withTwoFactor()->create();
    $previousCodes = $user->two_factor_recovery_codes;

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->post(route('two-factor.regenerate-recovery-codes'));

    $this->assertNotSame($previousCodes, $user->fresh()->two_factor_recovery_codes);
    $this->assertNotifiedOnce($user, SecurityEvent::RecoveryCodesRegenerated);
});

test('regenerating recovery codes with cancelled save does not notify', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    Notification::fake();
    $user = User::factory()->withTwoFactor()->create();
    $previousCodes = $user->two_factor_recovery_codes;
    User::saving(fn () => false);

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->post(route('two-factor.regenerate-recovery-codes'));

    expect($user->fresh()->two_factor_recovery_codes)->toBe($previousCodes);
    Notification::assertNothingSent();
});

test('deleting a passkey notifies the user once', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
    Notification::fake();
    $user = User::factory()->create();
    $passkey = $this->createPasskey($user);

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->delete(route('passkey.destroy', $passkey));

    $this->assertModelMissing($passkey);
    $this->assertNotifiedOnce($user, SecurityEvent::PasskeyRemoved);
});

test('deleting another users passkey is forbidden and does not notify', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
    Notification::fake();
    $user = User::factory()->create();
    $passkey = $this->createPasskey(User::factory()->create());

    $this->actingAs($user)->withSession($this->passwordConfirmed())
        ->delete(route('passkey.destroy', $passkey))
        ->assertForbidden();

    $this->assertModelExists($passkey);
    Notification::assertNothingSent();
});

test('registering an invalid passkey does not notify', function () {
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

    expect($user->passkeys()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a stored passkey registration notifies the user once', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
    Notification::fake();
    $user = User::factory()->create();

    event(new PasskeyRegistered($user, $this->createPasskey($user)));
    $this->runDeferredCallbacks();

    $this->assertNotifiedOnce($user, SecurityEvent::PasskeyAdded);
});

test('a passkey registration event for an unstored passkey does not notify', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
    Notification::fake();
    $user = User::factory()->create();

    event(new PasskeyRegistered($user, new Passkey));
    $this->runDeferredCallbacks();

    Notification::assertNothingSent();
});

test('email change notifies only the old address once', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->changeEmail($user, 'new@example.com', 'password')->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('new@example.com');
    Notification::assertCount(1);
    Notification::assertSentOnDemand(
        GeneralNotification::class,
        fn (GeneralNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notification->title === SecurityEvent::EmailChanged->subject()
            && $notification->body === SecurityEvent::EmailChanged->mailBody()
            && $channels === ['mail']
            && $notifiable->routes === ['mail' => 'old@example.com'],
    );
    Notification::assertNotSentTo($user, GeneralNotification::class);
});

test('email change with wrong password does not notify', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->changeEmail($user, 'new@example.com', 'wrong-password')->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->toBe('old@example.com');
    Notification::assertNothingSent();
});

test('email change to a taken address does not notify', function () {
    Notification::fake();
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->changeEmail($user, 'taken@example.com', 'password')->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

test('name only change does not notify', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'old@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('New Name');
    Notification::assertNothingSent();
});

test('email change succeeds even when notification fails', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $attempts = $this->failNotificationDelivery();

    $this->changeEmail($user, 'new@example.com', 'password')->assertSessionHasNoErrors();

    $this->assertDeliveryFailedWithoutAffectingOperation($attempts);
    expect($user->fresh()->email)->toBe('new@example.com');
});

test('email change with cancelled save does not notify', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);
    User::saving(fn () => false);

    $this->changeEmail($user, 'new@example.com', 'password')
        ->assertSessionHasErrors('profile')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('Profile could not be updated.')]);

    expect($user->fresh()->email)->toBe('old@example.com');
    Notification::assertNothingSent();
});

test('account deletion notifies the deleted address once', function () {
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
});

test('account deletion with wrong password does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    $this->assertModelExists($user);
    Notification::assertNothingSent();
});

test('account deletion succeeds even when notification fails', function () {
    $user = User::factory()->create();
    $attempts = $this->failNotificationDelivery();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    $this->assertDeliveryFailedWithoutAffectingOperation($attempts);
    $this->assertModelMissing($user);
});

test('account deletion with cancelled delete does not notify', function () {
    Notification::fake();
    $user = User::factory()->create();
    User::deleting(fn () => false);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('account')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('Your account could not be deleted.')]);

    $this->assertModelExists($user);
    Notification::assertNothingSent();
});

test('no notification mail contains links tokens or the new address', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    foreach (SecurityEvent::cases() as $event) {
        $mail = (new GeneralNotification($event->subject(), $event->mailBody()))->toMail($user);
        $rendered = strtolower($mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines));

        expect($mail->subject)->not->toBeEmpty($event->name);
        expect($mail->actionUrl)->toBeNull($event->name);
        $this->assertStringNotContainsString('http', $rendered, $event->name);
        $this->assertStringNotContainsString('token', $rendered, $event->name);
        $this->assertStringNotContainsString('@', $rendered, $event->name);
    }
});

// Helpers
