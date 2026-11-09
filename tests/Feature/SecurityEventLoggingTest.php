<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('changing the password records a security event', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-secret-value',
        'password_confirmation' => 'new-secret-value',
    ]);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('password.changed');
    expect($event->getProperty('result'))->toBe('success');
    expect($event->causer->is($user))->toBeTrue();
    expect($event->subject->is($user))->toBeTrue();
    $this->assertStringNotContainsString('new-secret-value', json_encode($event->properties));
});

test('a wrong current password records a failed password change', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-value',
        'password_confirmation' => 'new-secret-value',
    ]);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('password.change_failed');
    expect($event->getProperty('result'))->toBe('failure');
    expect($event->subject->is($user))->toBeTrue();
    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
    $this->assertStringNotContainsString('wrong-password', json_encode($event->properties));
    $this->assertStringNotContainsString('new-secret-value', json_encode($event->properties));
});

test('a cancelled password update is not recorded as a change', function () {
    $user = User::factory()->create();
    User::updating(fn () => false);

    try {
        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-value',
            'password_confirmation' => 'new-secret-value',
        ])->assertSessionHasErrors('security');
    } finally {
        User::flushEventListeners();
    }

    expect(Activity::inLog('security')->count())->toBe(0);
    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
});

test('resetting the password records a security event for the user', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'new-secret-value',
        'password_confirmation' => 'new-secret-value',
    ])->assertRedirect(route('login'));

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('password.reset');
    expect($event->subject->is($user))->toBeTrue();
    expect($event->causer)->toBeNull();
    expect(password_verify('new-secret-value', $user->fresh()->password))->toBeTrue();
});

test('an invalid reset token records a failed password reset', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-secret-value',
        'password_confirmation' => 'new-secret-value',
    ])->assertSessionHasErrors('email');

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('password.reset_failed');
    expect($event->getProperty('result'))->toBe('failure');
    expect($event->getProperty('reason'))->toBe('passwords.token');
    expect($event->subject->is($user))->toBeTrue();
    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
    $this->assertStringNotContainsString('invalid-token', json_encode($event->properties));
    $this->assertStringNotContainsString('new-secret-value', json_encode($event->properties));
});

test('a failing account lookup does not change the failed reset response', function () {
    $user = User::factory()->create();
    $lookups = 0;
    User::retrieved(function () use (&$lookups): void {
        if (++$lookups > 1) {
            throw new RuntimeException('lookup failed');
        }
    });

    try {
        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-secret-value',
            'password_confirmation' => 'new-secret-value',
        ])->assertSessionHasErrors('email');
    } finally {
        User::flushEventListeners();
    }

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('password.reset_failed');
    expect($event->subject_id)->toBeNull();
});

test('a reset for an unknown account records a failure without a subject', function () {
    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => 'nobody@example.com',
        'password' => 'new-secret-value',
        'password_confirmation' => 'new-secret-value',
    ])->assertSessionHasErrors('email');

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('password.reset_failed');
    expect($event->getProperty('reason'))->toBe('passwords.user');
    expect($event->subject_id)->toBeNull();
    $this->assertStringNotContainsString('nobody@example.com', json_encode($event->properties));
});

test('a cancelled password reset is not recorded', function () {
    $user = User::factory()->create();
    User::updating(fn () => false);

    try {
        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-secret-value',
            'password_confirmation' => 'new-secret-value',
        ])->assertStatus(500);
    } finally {
        User::flushEventListeners();
    }

    expect(Activity::inLog('security')->count())->toBe(0);
});

test('changing the email records a security event without email values', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'new@example.com',
        'current_password' => 'password',
    ]);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('email.changed');
    expect($event->subject->is($user))->toBeTrue();
    $this->assertStringNotContainsString('example.com', json_encode($event->properties));
    expect($user->fresh()->email)->toBe('new@example.com');
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('a wrong current password records a failed email change', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'new@example.com',
        'current_password' => 'wrong-password',
    ])->assertSessionHasErrors('current_password');

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('email.change_failed');
    expect($event->getProperty('result'))->toBe('failure');
    expect($event->subject->is($user))->toBeTrue();
    expect($user->fresh()->email)->toBe('old@example.com');
    $this->assertStringNotContainsString('example.com', json_encode($event->properties));
    $this->assertStringNotContainsString('wrong-password', json_encode($event->properties));
});

test('updating only the name does not record an email change', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Another Name',
        'email' => $user->email,
    ]);

    expect($user->fresh()->name)->toBe('Another Name');
    expect(Activity::inLog('security')->count())->toBe(0);
});

test('a cancelled profile update is not recorded as an email change', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    User::updating(fn () => false);

    try {
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
            'current_password' => 'password',
        ])->assertSessionHasErrors('profile');
    } finally {
        User::flushEventListeners();
    }

    expect($user->fresh()->email)->toBe('old@example.com');
    expect(Activity::inLog('security')->count())->toBe(0);
});

test('deleting the account records the user id without a subject', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    $event = Activity::inLog('security')->sole();

    $this->assertModelMissing($user);
    expect($event->description)->toBe('account.deleted');
    expect($event->subject_id)->toBeNull();
    expect($event->getProperty('user_id'))->toBe($user->id);
    expect($event->causer_id)->toBe($user->id);
});

test('a wrong password does not delete the account and records the failure', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'wrong-password']);

    $this->assertModelExists($user);

    $event = Activity::inLog('security')->sole();

    expect($event->description)->toBe('account.delete_failed');
    expect($event->getProperty('result'))->toBe('failure');
    expect($event->subject->is($user))->toBeTrue();
});

test('a cancelled account deletion is not recorded', function () {
    $user = User::factory()->create();
    User::deleting(fn () => false);

    try {
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('account');
    } finally {
        User::flushEventListeners();
    }

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
    expect(Activity::inLog('security')->count())->toBe(0);
});

test('a logging failure does not undo the password change', function () {
    $user = User::factory()->create();
    config(['activitylog.activity_model' => 'App\\Models\\MissingActivity']);

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-secret-value',
        'password_confirmation' => 'new-secret-value',
    ])->assertRedirect();

    expect(password_verify('new-secret-value', $user->fresh()->password))->toBeTrue();
});
