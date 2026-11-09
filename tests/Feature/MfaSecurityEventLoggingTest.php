<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\MfaSecurityEventLoggingHelpers;

uses(MfaSecurityEventLoggingHelpers::class);

uses(RefreshDatabase::class);

test('starting two factor setup is not recorded until it is confirmed', function () {
    $this->actingAsPasswordConfirmed()->post('/user/two-factor-authentication');

    expect(Activity::inLog('security')->count())->toBe(0);
});

test('confirming two factor records mfa enabled', function () {
    $user = $this->enableAndConfirmTwoFactor();

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('mfa.enabled');
    expect($event->subject->is($user))->toBeTrue();
    expect($event->causer->is($user))->toBeTrue();
});

test('disabling confirmed two factor records mfa disabled as active', function () {
    $user = $this->enableAndConfirmTwoFactor();
    Activity::inLog('security')->delete();

    $this->actingAsPasswordConfirmed($user)->delete('/user/two-factor-authentication');

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('mfa.disabled');
    expect($event->getProperty('was_active'))->toBeTrue();
});

test('cancelling an unconfirmed setup is recorded as not active', function () {
    $user = User::factory()->create();
    $this->actingAsPasswordConfirmed($user)->post('/user/two-factor-authentication');
    $this->actingAsPasswordConfirmed($user)->delete('/user/two-factor-authentication');

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('mfa.disabled');
    expect($event->getProperty('was_active'))->toBeFalse();
});

test('regenerating recovery codes is recorded without the codes', function () {
    $user = $this->enableAndConfirmTwoFactor();
    Activity::inLog('security')->delete();

    $this->actingAsPasswordConfirmed($user)->post('/user/two-factor-recovery-codes');

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('mfa.recovery_codes.regenerated');
    $this->assertStringNotContainsString($user->fresh()->recoveryCodes()[0], json_encode($event->properties));
});

test('a wrong confirmation code does not record mfa enabled', function () {
    $user = User::factory()->create();
    $this->actingAsPasswordConfirmed($user)->post('/user/two-factor-authentication');

    $this->actingAsPasswordConfirmed($user)->post('/user/confirmed-two-factor-authentication', ['code' => '000000']);

    expect(Activity::inLog('security')->count())->toBe(0);
});

test('cancelled user saves do not record mfa events', function () {
    $user = $this->enableAndConfirmTwoFactor();
    Activity::inLog('security')->delete();
    $codesBefore = $user->recoveryCodes();

    User::updating(fn () => false);

    try {
        $this->actingAsPasswordConfirmed($user)->post('/user/two-factor-recovery-codes');
        $this->actingAsPasswordConfirmed($user)->delete('/user/two-factor-authentication');
    } finally {
        User::flushEventListeners();
    }

    $fresh = $user->fresh();

    expect($fresh->recoveryCodes())->toBe($codesBefore);
    expect($fresh->two_factor_confirmed_at)->not->toBeNull();
    expect(Activity::inLog('security')->count())->toBe(0);
});

test('a cancelled confirmation save does not record mfa enabled', function () {
    $user = User::factory()->create();
    $this->actingAsPasswordConfirmed($user)->post('/user/two-factor-authentication');
    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));

    User::updating(fn () => false);

    try {
        $this->actingAsPasswordConfirmed($user)->post('/user/confirmed-two-factor-authentication', ['code' => $code]);
    } finally {
        User::flushEventListeners();
    }

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
    expect(Activity::inLog('security')->count())->toBe(0);
});

test('confirming an already confirmed two factor is not recorded again', function () {
    $user = $this->enableAndConfirmTwoFactor();
    Activity::inLog('security')->delete();

    Cache::flush();
    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->actingAsPasswordConfirmed($user)->post('/user/confirmed-two-factor-authentication', ['code' => $code]);

    expect(Activity::inLog('security')->count())->toBe(0);
});

test('reconfirming two factor on the same instance at the same instant is not recorded again', function () {
    $user = User::factory()->create();
    app(EnableTwoFactorAuthentication::class)($user);

    $this->travelTo(now());
    $otp = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    app(ConfirmTwoFactorAuthentication::class)($user, $otp);

    expect(Activity::inLog('security')->where('description', 'mfa.enabled')->count())->toBe(1);

    // Same instance, same frozen instant, same (now unused-again) code:
    // the forceFill() sets the exact value already stored, so this save
    // persists no real change.
    Cache::flush();
    app(ConfirmTwoFactorAuthentication::class)($user, $otp);

    expect(Activity::inLog('security')->where('description', 'mfa.enabled')->count())->toBe(1);
});

test('a cancelled save after a successful one on the same instance is not recorded', function () {
    $user = User::factory()->create();

    app(EnableTwoFactorAuthentication::class)($user);
    app(GenerateNewRecoveryCodes::class)($user);
    $recorded = Activity::inLog('security')->count();

    User::updating(fn () => false);

    try {
        app(GenerateNewRecoveryCodes::class)($user);
        app(DisableTwoFactorAuthentication::class)($user);
    } finally {
        User::flushEventListeners();
    }

    expect(Activity::inLog('security')->count())->toBe($recorded);
});
