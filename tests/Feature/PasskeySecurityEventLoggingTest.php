<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\PasskeySecurityEventLoggingHelpers;

uses(PasskeySecurityEventLoggingHelpers::class);

uses(RefreshDatabase::class);

test('a created passkey is recorded without its name', function () {
    $user = User::factory()->create();
    $passkey = $this->createPasskey($user, 'My secret laptop');

    PasskeyRegistered::dispatch($user, $passkey);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('passkey.created');
    expect($event->subject->is($user))->toBeTrue();
    expect($event->getProperty('passkey_id'))->toBe($passkey->id);
    $this->assertStringNotContainsString('My secret laptop', json_encode($event->properties));
});

test('a passkey that was not created is not recorded', function () {
    $user = User::factory()->create();
    Passkey::creating(fn () => false);

    try {
        $passkey = $this->createPasskey($user);
    } finally {
        Passkey::flushEventListeners();
    }

    PasskeyRegistered::dispatch($user, $passkey);

    expect(Activity::inLog('security')->count())->toBe(0);
});

test('deleting a passkey records the event', function () {
    $user = User::factory()->create();
    $passkey = $this->createPasskey($user);

    $this->actingAsPasswordConfirmed($user)->delete(route('passkey.destroy', $passkey));

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('passkey.deleted');
    expect($event->causer->is($user))->toBeTrue();
    expect($event->getProperty('passkey_id'))->toBe($passkey->id);
    $this->assertModelMissing($passkey);
});

test('a cancelled passkey delete is not recorded', function () {
    $user = User::factory()->create();
    $passkey = $this->createPasskey($user);
    Passkey::deleting(fn () => false);

    try {
        $this->actingAsPasswordConfirmed($user)->delete(route('passkey.destroy', $passkey));
    } finally {
        Passkey::flushEventListeners();
    }

    $this->assertModelExists($passkey);
    expect(Activity::inLog('security')->count())->toBe(0);
});

test('deleting another users passkey is forbidden and not recorded', function () {
    $owner = User::factory()->create();
    $passkey = $this->createPasskey($owner);

    $this->actingAsPasswordConfirmed(User::factory()->create())
        ->delete(route('passkey.destroy', $passkey))
        ->assertForbidden();

    $this->assertModelExists($passkey);
    expect(Activity::inLog('security')->count())->toBe(0);
});
