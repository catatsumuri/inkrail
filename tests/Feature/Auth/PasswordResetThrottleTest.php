<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\PasswordResetThrottleHelpers;

uses(PasswordResetThrottleHelpers::class);

uses(RefreshDatabase::class);

test('failed resets within the limit are all recorded', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->attemptReset($user->email)->assertSessionHasErrors('email');
    }

    expect(Activity::inLog('security')->where('event', 'password.reset_failed')->count())->toBe(5);
});

test('repeated attempts for one account are throttled without more log rows', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->attemptReset($user->email);
    }

    $this->attemptReset($user->email)->assertTooManyRequests();
    $this->attemptReset($user->email)->assertTooManyRequests();

    expect(Activity::inLog('security')->count())->toBe(5);
    expect(password_verify('password', $user->fresh()->password))->toBeTrue();
});

test('one address cannot flood the log by varying the email', function () {
    foreach (range(1, 10) as $attempt) {
        $this->attemptReset("nobody{$attempt}@example.com")->assertSessionHasErrors('email');
    }

    $this->attemptReset('nobody11@example.com')->assertTooManyRequests();

    expect(Activity::inLog('security')->count())->toBe(10);
});

test('a throttled account does not block other accounts', function () {
    $blocked = User::factory()->create();
    $other = User::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $this->attemptReset($blocked->email);
    }

    $this->attemptReset($other->email)->assertSessionHasErrors('email');
});

test('requesting a reset link is not affected by the reset limit', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $this->attemptReset($user->email);
    }

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
});
