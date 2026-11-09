<?php

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a security event cannot be updated', function () {
    $event = securityEvent();

    attempt(fn () => $event->update(['description' => 'tampered']));
    attempt(fn () => $event->forceFill(['properties' => ['result' => 'success']])->save());

    $stored = Activity::query()->findOrFail($event->getKey());

    expect($stored->description)->toBe('login.failed');
    expect($stored->getProperty('result'))->toBe('failure');
});

test('a security event cannot be deleted', function () {
    $event = securityEvent();

    attempt(fn () => $event->delete());
    attempt(fn () => $event->forceDelete());

    $this->assertModelExists($event);
});

test('renaming the log does not bypass the update guard', function () {
    $event = securityEvent();

    attempt(function () use ($event): void {
        $event->log_name = 'other';
        $event->description = 'tampered';
        $event->save();
    });

    $stored = Activity::query()->findOrFail($event->getKey());

    expect($stored->log_name)->toBe('security');
    expect($stored->description)->toBe('login.failed');
});

test('renaming the log does not bypass the delete guard', function () {
    $event = securityEvent();

    $event->log_name = 'other';
    attempt(fn () => $event->delete());

    $this->assertModelExists($event);
});

test('a record loaded from the database is guarded after renaming the log', function () {
    securityEvent();
    $loaded = Activity::query()->sole();

    $loaded->log_name = 'other';
    attempt(fn () => $loaded->delete());

    expect(Activity::query()->where('log_name', 'security')->count())->toBe(1);
});

test('deleting the subject user keeps its security events', function () {
    $user = User::factory()->create();
    $event = securityEvent($user);

    $user->delete();

    $this->assertModelExists($event);
});

test('the retention cleanup still deletes expired security events', function () {
    $expired = securityEvent();
    Activity::query()->whereKey($expired->getKey())->toBase()->update(['created_at' => now()->subDays(366)]);
    $recent = securityEvent();

    $this->artisan('activitylog:clean', ['log' => 'security', '--days' => 365, '--force' => true])->assertSuccessful();

    $this->assertModelMissing($expired);
    $this->assertModelExists($recent);
});

test('other logs are not affected by the guard', function () {
    $other = activity('other')->log('note');

    $other->update(['description' => 'edited']);

    expect($other->fresh()->description)->toBe('edited');
});

function securityEvent(?User $subject = null): Activity
{
    $logger = activity('security')->event('login.failed')->withProperties(['result' => 'failure']);

    if ($subject !== null) {
        $logger->performedOn($subject);
    }

    return $logger->log('login.failed');
}

function attempt(callable $action): void
{
    try {
        $action();
    } catch (Throwable) {
        // The guard may reject by exception or by returning false; only the stored row matters.
    }
}
