<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

test('reset is disabled outside the live demo and preserves existing data', function () {
    config(['app.demo_mode' => false]);
    $user = User::factory()->create();

    $this->artisan('demo:reset')->assertFailed();

    $this->assertModelExists($user);
});

test('demo reset refuses non SQLite connections', function () {
    $defaultConnection = config('database.default');
    config(['app.demo_mode' => true, 'database.default' => 'mysql']);

    try {
        $this->artisan('demo:reset')->assertFailed();
    } finally {
        config(['database.default' => $defaultConnection]);
    }
});

test('production demo reset removes visitor data and restores seed data while keeping destructive commands prohibited', function () {
    config(['app.demo_mode' => true]);
    $this->app->detectEnvironment(fn (): string => 'production');
    $user = User::factory()->create(['email' => 'visitor@example.com']);
    $user->articles()->create(['title' => 'Visitor draft', 'content' => 'Visitor content', 'slug' => 'visitor-draft']);

    try {
        DB::prohibitDestructiveCommands();
        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'visitor@example.com']);
        $this->assertDatabaseMissing('articles', ['slug' => 'visitor-draft']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('articles', 8);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        expect(Article::where('slug', 'showcase-markdown')->sole()->is_published)->toBeTrue();
        $this->artisan('migrate:fresh', ['--force' => true])->assertFailed();
        $this->artisan('db:wipe', ['--force' => true])->assertFailed();
    } finally {
        DB::prohibitDestructiveCommands(false);
        $this->app->detectEnvironment(fn (): string => 'testing');
    }
});

test('demo resets are due only at 8 and 20 Japan time', function (string $time, bool $isDue) {
    config(['app.demo_mode' => true]);
    $this->travelTo(new DateTimeImmutable($time));
    require base_path('routes/console.php');
    $this->artisan('schedule:list')->assertSuccessful();
    $event = collect(app(Schedule::class)->events())
        ->sole(fn ($event): bool => str_contains($event->command ?? '', 'demo:reset'));

    expect($event->isDue($this->app))->toBe($isDue);
})->with([
    ['2026-11-10T08:00:00+09:00', true],
    ['2026-11-10T20:00:00+09:00', true],
    ['2026-11-10T08:01:00+09:00', false],
    ['2026-11-10T17:00:00+09:00', false],
]);

test('demo reset is not scheduled when demo mode is disabled', function () {
    config(['app.demo_mode' => false]);

    $this->artisan('schedule:list')->doesntExpectOutputToContain('demo:reset')->assertSuccessful();
});
