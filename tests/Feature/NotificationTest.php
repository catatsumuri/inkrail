<?php

use App\Actions\Notifications\SendGeneralNotification;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a notification can be stored for a user', function () {
    $user = User::factory()->create();

    (new SendGeneralNotification)->handle(
        $user,
        'Maintenance scheduled',
        'The service will be unavailable tonight.',
        '/dashboard',
    );

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(GeneralNotification::class);
    expect($notification->data)->toBe([
        'title' => 'Maintenance scheduled',
        'body' => 'The service will be unavailable tonight.',
        'action_url' => '/dashboard',
    ]);
    expect($notification->read_at)->toBeNull();
});

test('an insecure action url is rejected', function () {
    $user = User::factory()->create();

    $this->expectException(InvalidArgumentException::class);

    (new SendGeneralNotification)->handle(
        $user,
        'Important update',
        'Review this update.',
        'http://example.com/update',
    );
});

test('a user can mark their notification as read', function () {
    $user = User::factory()->create();
    $user->notify(new GeneralNotification('Welcome', 'Thanks for joining.', '/dashboard'));
    $notification = $user->notifications()->sole();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification))
        ->assertRedirect('/dashboard');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot read another users notification', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUser->notify(new GeneralNotification('Private', 'For the recipient only.'));
    $notification = $otherUser->notifications()->sole();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('a user can delete their notification', function () {
    $user = User::factory()->create();
    $user->notify(new GeneralNotification('Welcome', 'Thanks for joining.'));
    $notification = $user->notifications()->sole();

    $this->actingAs($user)
        ->delete(route('notifications.destroy', $notification))
        ->assertRedirect();

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

test('a user cannot delete another users notification', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUser->notify(new GeneralNotification('Private', 'For the recipient only.'));
    $notification = $otherUser->notifications()->sole();

    $this->actingAs($user)
        ->delete(route('notifications.destroy', $notification))
        ->assertNotFound();

    $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
});

test('an alert is sent to the configured mail and slack routes', function () {
    Notification::fake();
    config([
        'services.alerts.mail_to' => 'ops@example.com',
        'services.alerts.slack_webhook_url' => 'https://hooks.slack.com/services/T/B/X',
    ]);

    $sent = (new SendGeneralNotification)->toAlertRecipients('Down', 'Queue is failing.', channels: ['mail', 'slack']);

    expect($sent)->toBe(['mail', 'slack']);
    Notification::assertSentOnDemand(
        GeneralNotification::class,
        function (GeneralNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $channels === ['mail', 'slack']
                && $notifiable->routes['mail'] === 'ops@example.com'
                && $notifiable->routes['slack'] === 'https://hooks.slack.com/services/T/B/X';
        },
    );
});

test('an alert skips unconfigured channels', function () {
    Notification::fake();
    config([
        'services.alerts.mail_to' => 'ops@example.com',
        'services.alerts.slack_webhook_url' => null,
    ]);

    $sent = (new SendGeneralNotification)->toAlertRecipients('Down', 'Queue is failing.');

    expect($sent)->toBe(['mail']);
});

test('an alert rejects an insecure action url', function () {
    $this->expectException(InvalidArgumentException::class);

    (new SendGeneralNotification)->toAlertRecipients('Down', 'Body', 'http://example.com');
});

test('the alert command sends to requested channels', function () {
    Notification::fake();
    config([
        'services.alerts.mail_to' => 'ops@example.com',
        'services.alerts.slack_webhook_url' => 'https://hooks.slack.com/services/T/B/X',
    ]);

    $this->artisan('notifications:send', [
        '--alert' => true,
        '--channel' => ['mail'],
        '--title' => 'Test',
        '--body' => 'Test alert',
        '--url' => '',
    ])->assertSuccessful();

    Notification::assertSentOnDemand(
        GeneralNotification::class,
        fn (GeneralNotification $notification, array $channels): bool => $channels === ['mail'],
    );
});

test('the alert command fails without recipients', function () {
    config([
        'services.alerts.mail_to' => null,
        'services.alerts.slack_webhook_url' => null,
    ]);

    $this->artisan('notifications:send', [
        '--alert' => true,
        '--title' => 'Test',
        '--body' => 'Test alert',
        '--url' => '',
    ])->assertFailed();
});

test('reported exceptions trigger a throttled alert', function () {
    Notification::fake();
    Cache::flush();
    config(['services.alerts.mail_to' => 'ops@example.com']);

    $exception = new RuntimeException('Boom');

    report($exception);
    report($exception);

    Notification::assertSentOnDemandTimes(GeneralNotification::class, 1);
});

test('an alert is still sent when the cache is unavailable', function () {
    Notification::fake();
    config(['services.alerts.mail_to' => 'ops@example.com']);
    Cache::shouldReceive('add')->once()->andThrow(new RuntimeException('cache down'));

    report(new RuntimeException('Boom'));

    Notification::assertSentOnDemandTimes(GeneralNotification::class, 1);
});

test('the exception command sends through the throwable alert path', function () {
    Notification::fake();
    Cache::flush();
    config(['services.alerts.mail_to' => 'ops@example.com']);

    $this->artisan('notifications:send', ['--exception' => true, '--body' => 'Probe'])
        ->assertSuccessful();

    $this->artisan('notifications:send', ['--exception' => true, '--body' => 'Probe'])
        ->assertFailed();

    $this->artisan('notifications:send', [
        '--exception' => true,
        '--no-throttle' => true,
        '--body' => 'Probe',
    ])->assertSuccessful();

    Notification::assertSentOnDemandTimes(GeneralNotification::class, 2);
});
