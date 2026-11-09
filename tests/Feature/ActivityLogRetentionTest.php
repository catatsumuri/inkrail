<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('activity log cleanup is scheduled daily per log with configured days', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain("activitylog:clean 'security' --days=365 --force")
        ->assertSuccessful();
});

test('cleaning the security log only deletes old security records', function () {
    $oldSecurity = activityAt('security', 366);
    $recentSecurity = activityAt('security', 364);
    $oldOther = activityAt('other', 366);

    $this->artisan('activitylog:clean', ['log' => 'security', '--days' => 365, '--force' => true])->assertSuccessful();

    $this->assertModelMissing($oldSecurity);
    $this->assertModelExists($recentSecurity);
    $this->assertModelExists($oldOther);
});

test('invalid retention days do not schedule cleanup', function () {
    foreach (['abc', '0', '-5', '', 'true', 'false', 'null'] as $invalid) {
        // putenv() too: when .env already sets this key (as .env.example
        // does), env() prefers the value putenv() captured at the very
        // first boot over a plain $_ENV/$_SERVER assignment, so without
        // this the override is silently ignored and the test can pass
        // locally (no .env entry to compete with) while failing in CI
        // (a real .env copied from .env.example) or vice versa.
        putenv("AUDIT_SECURITY_RETENTION_DAYS={$invalid}");
        $_ENV['AUDIT_SECURITY_RETENTION_DAYS'] = $_SERVER['AUDIT_SECURITY_RETENTION_DAYS'] = $invalid;
        // Env::$repository is a process-wide static singleton, built once
        // and reused by every later env()/Dotenv::safeLoad() call
        // regardless of refreshApplication(), so it never notices the
        // reassignment above on its own. enablePutenv() is Laravel's only
        // public hook that resets it (a side effect of its real job),
        // forcing a fresh repository that actually reads the new value.
        Env::enablePutenv();
        $this->refreshApplication();

        $this->artisan('schedule:list')
            ->doesntExpectOutputToContain("activitylog:clean 'security'")
            ->assertSuccessful();
    }
});

afterEach(function () {
    putenv('AUDIT_SECURITY_RETENTION_DAYS');
    unset($_ENV['AUDIT_SECURITY_RETENTION_DAYS'], $_SERVER['AUDIT_SECURITY_RETENTION_DAYS']);

    // Drop the stale Env::$repository too, so it doesn't leak this
    // test's last override into whichever test class runs next in the
    // same process.
    Env::enablePutenv();

});

function activityAt(string $logName, int $daysAgo): Activity
{
    $activity = activity($logName)->log('test');
    Activity::query()->whereKey($activity->getKey())->toBase()->update(['created_at' => now()->subDays($daysAgo)]);

    return $activity;
}
