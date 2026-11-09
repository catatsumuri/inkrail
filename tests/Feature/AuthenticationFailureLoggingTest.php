<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('a wrong password records a failed login for the account', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('login.failed');
    expect($event->getProperty('result'))->toBe('failure');
    expect($event->subject->is($user))->toBeTrue();
    expect($event->causer)->toBeNull();
    $this->assertStringNotContainsString('wrong-password', json_encode($event->properties));
    $this->assertStringNotContainsString($user->email, json_encode($event->properties));
});

test('an unknown account records a failed login without a subject', function () {
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('login.failed');
    expect($event->subject_id)->toBeNull();
    $this->assertStringNotContainsString('nobody@example.com', json_encode($event->properties));
});

test('a successful login does not record a failure', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect(Activity::inLog('security')->count())->toBe(0);
});

test('a wrong two factor code records a failed mfa attempt', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create([
        'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
    ]);

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['code' => '000000']);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('mfa.failed');
    expect($event->getProperty('result'))->toBe('failure');
    expect($event->getProperty('method'))->toBe('totp');
    expect($event->subject->is($user))->toBeTrue();
    $this->assertStringNotContainsString('000000', json_encode($event->properties));
});

test('a wrong recovery code records the recovery code method', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->withTwoFactor()->create();

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), ['recovery_code' => 'not-a-real-code']);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('mfa.failed');
    expect($event->getProperty('method'))->toBe('recovery_code');
    $this->assertStringNotContainsString('not-a-real-code', json_encode($event->properties));
});
