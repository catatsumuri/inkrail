<?php

use App\Actions\Sessions\ManageDatabaseSessions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\Concerns\RevokeUserSessionsCommandHelpers;

uses(RefreshDatabase::class);

uses(RevokeUserSessionsCommandHelpers::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->app['session']->forgetDrivers();
});

/**
 * @return array<string, array{string}>
 */
dataset('invalidSelectors', function () {
    return [
        'bare number' => ['1'],
        'bare email' => ['user@example.com'],
        'unknown type' => ['name:alice'],
        'empty value' => ['id:'],
        'blank value' => ['email:   '],
        'zero id' => ['id:0'],
        'negative id' => ['id:-1'],
        'non numeric id' => ['id:1x'],
        'id just above the integer range' => ['id:9223372036854775808'],
        'id far above the integer range' => ['id:99999999999999999999999'],
        'zero padded zero id' => ['id:000'],
    ];
});

test('a selector must state its type and a valid value', function (string $selector) {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);

    $this->artisan('security:revoke-sessions', ['selector' => $selector, '--force' => true])
        ->expectsOutputToContain('Use id:123 or email:user@example.com.')
        ->assertFailed();

    $this->assertDatabaseHas('sessions', ['id' => 'session-one']);
})->with('invalidSelectors');

test('an unknown user fails without touching other sessions', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:999999', '--force' => true])
        ->expectsOutputToContain('The specified user was not found.')
        ->assertFailed();

    $this->artisan('security:revoke-sessions', ['selector' => 'email:nobody@example.com', '--force' => true])
        ->expectsOutputToContain('The specified user was not found.')
        ->assertFailed();

    $this->assertDatabaseHas('sessions', ['id' => 'session-one']);
});

test('declining the confirmation preserves every session', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);
    $this->storeSession('session-two', $user);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey()])
        ->expectsConfirmation($this->confirmationQuestion($user, 2), 'no')
        ->expectsOutputToContain('No sessions were revoked.')
        ->assertSuccessful();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);
});

test('confirming revokes every session of the user', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);
    $this->storeSession('session-two', $user);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey()])
        ->expectsConfirmation($this->confirmationQuestion($user, 2), 'yes')
        ->expectsOutputToContain('Revoked 2 session(s) for user ID '.$user->getKey().'.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
});

test('force revokes only the target users sessions including expired rows', function () {
    $user = User::factory()->create(['email' => 'target@example.com']);
    $otherUser = User::factory()->create();
    $this->storeSession('active-session', $user);
    $this->storeSession('expired-session', $user, now()->subDay()->timestamp);
    $this->storeSession('other-users-session', $otherUser);
    $this->storeSession('guest-session', null);

    $this->artisan('security:revoke-sessions', ['selector' => 'email:  Target@Example.com ', '--force' => true])
        ->expectsOutputToContain('Revoked 2 session(s) for user ID '.$user->getKey().'.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('sessions', ['id' => 'active-session']);
    $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-users-session', 'user_id' => $otherUser->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'guest-session']);
});

test('a user without stored sessions still requires confirmation before remember me is reset', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => 'original-token'])->save();

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey()])
        ->expectsConfirmation($this->emptyConfirmationQuestion($user), 'no')
        ->expectsOutputToContain('No sessions were revoked.')
        ->assertSuccessful();

    expect($user->fresh()->remember_token)->toBe('original-token');
});

test('confirming for a user without stored sessions resets remember me only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $user->forceFill(['remember_token' => 'original-token'])->save();
    $this->storeSession('other-users-session', $otherUser);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey()])
        ->expectsConfirmation($this->emptyConfirmationQuestion($user), 'yes')
        ->expectsOutputToContain('Remember me logins were reset.')
        ->assertSuccessful();

    $this->assertNotSame('original-token', $user->fresh()->remember_token);
    $this->assertDatabaseHas('sessions', ['id' => 'other-users-session']);
});

test('force skips the confirmation for a user without stored sessions', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => 'original-token'])->save();

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Remember me logins were reset.')
        ->assertSuccessful();

    $this->assertNotSame('original-token', $user->fresh()->remember_token);
});

test('revocation signs out a logged in browser', function () {
    $user = User::factory()->create();
    $bystander = User::factory()->create();
    $cookie = $this->signIn($user);
    $bystanderCookie = $this->signIn($bystander);

    $this->asClient($cookie)->get(route('dashboard'))->assertOk();

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->assertSuccessful();

    $this->asClient($cookie)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($bystanderCookie)->get(route('dashboard'))->assertOk();
});

test('it fails safely for a non database session driver', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);

    config(['session.driver' => 'array']);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Session revocation requires the database session driver.')
        ->assertFailed();

    $this->assertDatabaseHas('sessions', ['id' => 'session-one']);
});

test('it fails without claiming success when counting sessions fails', function () {
    $user = User::factory()->create();

    $this->mock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isAvailable')->andReturnTrue();
        $mock->shouldReceive('storedSessionCount')->andThrow(new RuntimeException('Storage unavailable'));
        $mock->shouldNotReceive('revokeAllSessions');
    });

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Session storage is unavailable. No sessions were revoked.')
        ->assertFailed();
});

test('it fails without claiming success when deleting sessions fails', function () {
    $user = User::factory()->create();

    $this->mock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isAvailable')->andReturnTrue();
        $mock->shouldReceive('storedSessionCount')->andReturn(2);
        $mock->shouldReceive('resetRememberToken')->once();
        $mock->shouldReceive('revokeAllSessions')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Remember me logins were reset, but session storage is unavailable. No sessions were revoked.')
        ->doesntExpectOutputToContain('Revoked')
        ->assertFailed();
});

test('the largest integer id is a valid selector for a missing user', function () {
    $this->artisan('security:revoke-sessions', ['selector' => 'id:9223372036854775807', '--force' => true])
        ->expectsOutputToContain('The specified user was not found.')
        ->assertFailed();
});

test('a zero padded id selects the same user', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:00'.$user->getKey(), '--force' => true])
        ->assertSuccessful();

    $this->assertDatabaseMissing('sessions', ['id' => 'session-one']);
});

test('an email stored with mixed case is found regardless of the selector case', function () {
    $user = User::factory()->create(['email' => 'Mixed.Case@Example.com']);
    $this->storeSession('session-one', $user);

    $this->artisan('security:revoke-sessions', ['selector' => 'email:MIXED.case@example.COM', '--force' => true])
        ->expectsOutputToContain('Revoked 1 session(s) for user ID '.$user->getKey().'.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('sessions', ['id' => 'session-one']);
});

test('an email matching several users is refused without revoking anything', function () {
    $first = User::factory()->create(['email' => 'twin@example.com']);
    $second = User::factory()->create(['email' => 'Twin@Example.com']);
    $this->storeSession('first-session', $first);
    $this->storeSession('second-session', $second);

    $this->artisan('security:revoke-sessions', ['selector' => 'email:twin@example.com', '--force' => true])
        ->expectsOutputToContain('More than one user matches')
        ->assertFailed();

    $this->assertDatabaseHas('sessions', ['id' => 'first-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'second-session']);
});

test('revocation also invalidates remember me cookies', function () {
    $user = User::factory()->create();
    $bystander = User::factory()->create();
    $remembered = $this->signInRemembered($user);
    $bystanderRemembered = $this->signInRemembered($bystander);
    $originalToken = $user->fresh()->remember_token;
    $bystanderToken = $bystander->fresh()->remember_token;

    expect($originalToken)->not->toBeEmpty();
    $this->asClient(null, $remembered)->get(route('dashboard'))->assertOk();

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->assertSuccessful();

    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->asClient(null, $remembered)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);

    $this->asClient(null, $bystanderRemembered)->get(route('dashboard'))->assertOk();
    expect($bystander->fresh()->remember_token)->toBe($bystanderToken);
});

test('remember me cookies are invalidated even when no session row is stored', function () {
    $user = User::factory()->create();
    $remembered = $this->signInRemembered($user);
    DB::table('sessions')->where('user_id', $user->id)->delete();
    $originalToken = $user->fresh()->remember_token;

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey()])
        ->expectsConfirmation($this->emptyConfirmationQuestion($user), 'yes')
        ->expectsOutputToContain('No sessions are stored for')
        ->assertSuccessful();

    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient(null, $remembered)->get(route('dashboard'))->assertRedirect(route('login'));
});

test('declining the confirmation keeps remember me cookies valid', function () {
    $user = User::factory()->create();
    $remembered = $this->signInRemembered($user);
    $originalToken = $user->fresh()->remember_token;
    $count = DB::table('sessions')->where('user_id', $user->id)->count();

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey()])
        ->expectsConfirmation($this->confirmationQuestion($user, $count), 'no')
        ->assertSuccessful();

    expect($user->fresh()->remember_token)->toBe($originalToken);
    $this->asClient(null, $remembered)->get(route('dashboard'))->assertOk();
});

test('a failure to reset the remember token is reported as a failure', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);

    $originalToken = $user->fresh()->remember_token;

    User::updating(function (): bool {
        throw new RuntimeException('Users unavailable');
    });

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Remember me logins could not be reset. No sessions were revoked.')
        ->doesntExpectOutputToContain('Revoked')
        ->assertFailed();

    $this->assertDatabaseHas('sessions', ['id' => 'session-one']);
    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a declined token update is reported as a failure', function () {
    $user = User::factory()->create();
    $this->storeSession('session-one', $user);

    $originalToken = $user->fresh()->remember_token;

    User::updating(fn (): bool => false);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Remember me logins could not be reset. No sessions were revoked.')
        ->doesntExpectOutputToContain('Revoked')
        ->assertFailed();

    $this->assertDatabaseHas('sessions', ['id' => 'session-one']);
    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a declined token update is reported as a failure with no stored sessions', function () {
    $user = User::factory()->create();
    $originalToken = $user->fresh()->remember_token;

    User::updating(fn (): bool => false);

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Remember me logins could not be reset. No sessions were revoked.')
        ->doesntExpectOutputToContain('No sessions are stored for')
        ->assertFailed();

    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a failure to delete sessions reports that remember me logins were already reset', function () {
    $user = User::factory()->create();
    $user->forceFill(['remember_token' => 'original-token'])->save();
    $this->storeSession('session-one', $user);

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('revokeAllSessions')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->artisan('security:revoke-sessions', ['selector' => 'id:'.$user->getKey(), '--force' => true])
        ->expectsOutputToContain('Remember me logins were reset, but session storage is unavailable. No sessions were revoked.')
        ->doesntExpectOutputToContain('Revoked')
        ->assertFailed();

    $this->assertNotSame('original-token', $user->fresh()->remember_token);
    $this->assertDatabaseHas('sessions', ['id' => 'session-one']);
});
