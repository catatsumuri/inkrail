<?php

use App\Actions\Sessions\ManageDatabaseSessions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\Concerns\SessionManagementHelpers;

uses(RefreshDatabase::class);

uses(SessionManagementHelpers::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->app['session']->forgetDrivers();
});

test('user can view their current and other active sessions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $current = $this->signIn($user);
    $this->storeSession('other-session', $user, '203.0.113.42', 'Mozilla/5.0 (Windows NT 10.0) Firefox/130.0');
    $this->storeSession('expired-session', $user, '203.0.113.50', 'Expired', now()->subHours(3)->timestamp);
    $this->storeSession('foreign-session', $otherUser, '203.0.113.60', 'Foreign');

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])
        ->get(route('sessions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->where('available', true)
            ->where('currentSession.is_current', true)
            ->where('currentSession.fingerprint', app(ManageDatabaseSessions::class)->fingerprint($user, $current['id']))
            ->has('otherSessions.data', 1)
            ->where('otherSessions.data.0.device', 'Firefox')
            ->where('otherSessions.data.0.ip_address', '203.0.113.x')
            ->where('otherSessions.data.0.is_current', false),
        );
});

test('guests cannot access session management', function () {
    $this->get(route('sessions.index'))->assertRedirect(route('login'));
    $this->delete(route('sessions.destroy-others'))->assertRedirect(route('login'));
});

test('the session page itself requires recent password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sessions.index'))
        ->assertRedirect(route('password.confirm'));

    foreach ([16, 60] as $minutes) {
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->subMinutes($minutes)->timestamp])
            ->get(route('sessions.index'))
            ->assertRedirect(route('password.confirm'));
    }

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->subMinutes(14)->timestamp])
        ->get(route('sessions.index'))
        ->assertOk();
});

test('confirming the password returns to the page and revocation then needs no second prompt', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $other = $this->signIn($user);

    $this->asClient($current['cookie'])
        ->get(route('sessions.index'))
        ->assertRedirect(route('password.confirm'));

    $this->asClient($current['cookie'])
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('sessions.index'));

    $this->asClient($current['cookie'])->get(route('sessions.index'))->assertOk();
    $this->asClient($current['cookie'])->delete(route('sessions.destroy-others'))->assertRedirect();

    $this->asClient($other['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
});

test('viewing the session page is not throttled like revocation', function () {
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('sessions.index'))
            ->assertOk();
    }
});

test('session presenter marks the current session and masks its details', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->storeSession(
        'current-session',
        $user,
        '2001:db8:abcd:12:1234:5678:90ab:cdef',
        'Mozilla/5.0 (Macintosh) Chrome/130.0 Safari/537.36',
    );
    $this->storeSession(
        'other-session',
        $user,
        '203.0.113.42',
        'Mozilla/5.0 (Windows NT 10.0) Firefox/130.0',
    );
    $this->storeSession(
        'expired-session',
        $user,
        lastActivity: now()->subHours(3)->timestamp,
    );
    $this->storeSession('foreign-session', $otherUser);

    $sessions = app(ManageDatabaseSessions::class)->sessions($user, 'current-session');

    expect($sessions['currentSession'])->toBeArray();
    expect($sessions['currentSession'])->toBe([
        'fingerprint' => app(ManageDatabaseSessions::class)->fingerprint($user, 'current-session'),
        'device' => 'Chrome',
        'platform' => 'macOS',
        'ip_address' => '2001:db8:abcd:12::/64',
        'last_activity' => $sessions['currentSession']['last_activity'],
        'is_current' => true,
    ]);
    expect($sessions['otherSessions']->total())->toBe(1);
    expect($sessions['otherSessions']->items()[0]['device'])->toBe('Firefox');
    expect($sessions['otherSessions']->items()[0]['platform'])->toBe('Windows');
    expect($sessions['otherSessions']->items()[0]['ip_address'])->toBe('203.0.113.x');
});

test('session page is safe when database sessions are not available', function () {
    config(['session.driver' => 'array']);
    $this->app['session']->forgetDrivers();

    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('sessions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->where('available', false)
            ->where('currentSession', null)
            ->where('otherSessions', null),
        );
});

test('session page is safe when session storage is inaccessible', function () {
    $this->mock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isAvailable')->once()->andReturnTrue();
        $mock->shouldReceive('sessions')->once()->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('sessions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->where('available', false)
            ->where('currentSession', null)
            ->where('otherSessions', null),
        );
});

test('other sessions are paginated', function () {
    $user = User::factory()->create();

    foreach (range(1, 21) as $index) {
        $this->storeSession('other-session-'.$index, $user);
    }

    $sessions = app(ManageDatabaseSessions::class)->sessions($user, 'current-session');

    expect($sessions['otherSessions']->items())->toHaveCount(20);
    expect($sessions['otherSessions']->total())->toBe(21);
    expect($sessions['otherSessions']->lastPage())->toBe(2);
});

test('user can revoke another session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->storeSession('other-session', $user);

    $fingerprint = app(ManageDatabaseSessions::class)->fingerprint($user, 'other-session');

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('sessions.destroy', $fingerprint))
        ->assertRedirect();

    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
});

test('user cannot revoke another users session', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);
    $this->storeSession('foreign-session', $otherUser);

    $fingerprint = app(ManageDatabaseSessions::class)->fingerprint($otherUser, 'foreign-session');

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('sessions.destroy', $fingerprint))
        ->assertNotFound();

    $this->assertDatabaseHas('sessions', [
        'id' => 'foreign-session',
        'user_id' => $otherUser->id,
    ]);
});

test('user can revoke all other sessions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->storeSession('other-session-one', $user);
    $this->storeSession('other-session-two', $user);

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('sessions.destroy-others'))
        ->assertRedirect();

    $this->assertDatabaseMissing('sessions', ['id' => 'other-session-one']);
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session-two']);
    $this->assertAuthenticatedAs($user);
});

test('only active sessions can be revoked individually', function () {
    $user = User::factory()->create();
    $this->storeSession('expired-session', $user, lastActivity: now()->subHours(3)->timestamp);
    $sessions = app(ManageDatabaseSessions::class);

    $revoked = $sessions->revoke($user, 'current-session', $sessions->fingerprint($user, 'expired-session'));

    expect($revoked)->toBeFalse();
});

test('revoking other sessions preserves the supplied current session', function () {
    $user = User::factory()->create();
    $this->storeSession('current-session', $user);
    $this->storeSession('other-session', $user);

    $revokedCount = app(ManageDatabaseSessions::class)
        ->revokeOtherSessions($user, 'current-session');

    expect($revokedCount)->toBe(1);
    $this->assertDatabaseHas('sessions', ['id' => 'current-session']);
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
});

test('deleting a database row invalidates every copy of the session id', function () {
    $user = User::factory()->create();
    $sessionId = str_repeat('a', 40);
    $handler = new DatabaseSessionHandler(
        DB::connection(),
        'sessions',
        (int) config('session.lifetime'),
        $this->app,
    );
    $session = new Store(
        (string) config('session.cookie'),
        $handler,
        $sessionId,
        'json',
    );
    $authKey = Auth::guard('web')->getName();
    $session->put($authKey, $user->id);
    $session->save();

    $copiedSession = new Store(
        (string) config('session.cookie'),
        new DatabaseSessionHandler(
            DB::connection(),
            'sessions',
            (int) config('session.lifetime'),
            $this->app,
        ),
        $sessionId,
        'json',
    );
    $copiedSession->start();

    expect($copiedSession->get($authKey))->toBe($user->id);

    DB::table('sessions')->where('id', $sessionId)->delete();

    foreach (range(1, 2) as $copy) {
        $revokedSession = new Store(
            (string) config('session.cookie').$copy,
            new DatabaseSessionHandler(
                DB::connection(),
                'sessions',
                (int) config('session.lifetime'),
                $this->app,
            ),
            $sessionId,
            'json',
        );
        $revokedSession->start();

        expect($revokedSession->has($authKey))->toBeFalse();
    }
});

test('user can revoke all sessions including the current session', function () {
    $user = User::factory()->create();
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->actingAs($user)
        ->get(route('dashboard'));
    $this->storeSession('other-session', $user);

    $this->delete(route('sessions.destroy-all'))
        ->assertRedirect(route('home'));

    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertGuest();
});

/**
 * Passkey re-authentication is not exercised here: the passkey confirmation
 * endpoint sets the same `auth.password_confirmed_at` session value that these
 * tests set. Covering it needs a test key pair or a virtual WebAuthn
 * authenticator to sign a valid assertion, so it is tracked as a follow-up
 * rather than covered by this branch.
 *
 * @return array<string, array{string, string}>
 */
dataset('revocationEndpoints', function () {
    return [
        'individual' => ['sessions.destroy', 'invalid-fingerprint'],
        'other sessions' => ['sessions.destroy-others', ''],
        'all sessions' => ['sessions.destroy-all', ''],
    ];
});

test('revocation is unavailable for non database session drivers', function (string $route, string $parameter) {
    config(['session.driver' => 'array']);
    $this->app['session']->forgetDrivers();

    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete($this->revocationUrl($route, $parameter))
        ->assertNotFound();

    $this->assertAuthenticated();
})->with('revocationEndpoints');

test('revocation requires password confirmation within fifteen minutes', function (string $route, string $parameter) {
    $user = User::factory()->create();
    $this->storeSession('other-session', $user);

    // Stricter than the three hour `auth.password_timeout` default.
    foreach ([16, 60, 179] as $minutes) {
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->subMinutes($minutes)->timestamp])
            ->delete($this->revocationUrl($route, $parameter))
            ->assertRedirect(route('password.confirm'));
    }

    $this->assertAuthenticated();
    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
})->with('revocationEndpoints');

test('revocation without any confirmation is redirected to confirm password', function (string $route, string $parameter) {
    $user = User::factory()->create();
    $this->storeSession('other-session', $user);

    $this->actingAs($user)
        ->delete($this->revocationUrl($route, $parameter))
        ->assertRedirect(route('password.confirm'));

    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
})->with('revocationEndpoints');

test('revocation is allowed within the confirmation window', function () {
    $user = User::factory()->create();
    $this->storeSession('other-session', $user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->subMinutes(14)->timestamp])
        ->delete(route('sessions.destroy-others'))
        ->assertRedirect();

    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
});

test('revocation endpoints are rate limited', function (string $route, string $parameter) {
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->delete($this->revocationUrl($route, $parameter));

        $this->assertNotSame(429, $response->getStatusCode(), 'Attempt '.($attempt + 1).' was throttled.');
    }

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete($this->revocationUrl($route, $parameter))
        ->assertTooManyRequests();
})->with('revocationEndpoints');

test('unknown and foreign fingerprints get the same response', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->storeSession('foreign-session', $otherUser);
    $foreign = app(ManageDatabaseSessions::class)->fingerprint($otherUser, 'foreign-session');
    $forged = app(ManageDatabaseSessions::class)->fingerprint($user, 'foreign-session');

    $responses = [];

    foreach ([$foreign, $forged, 'unknown', str_repeat('a', 5000), 'é%00'] as $fingerprint) {
        $responses[] = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->delete(route('sessions.destroy', $fingerprint));
    }

    foreach ($responses as $response) {
        $response->assertNotFound();
        expect($response->getContent())->toBe($responses[0]->getContent());
    }

    $this->assertDatabaseHas('sessions', ['id' => 'foreign-session', 'user_id' => $otherUser->id]);
});

test('session ids are not exposed to the frontend', function () {
    $user = User::factory()->create();
    $this->storeSession('secret-other-session-id', $user);

    $content = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('sessions.index'))
        ->assertOk()
        ->getContent();

    $this->assertStringNotContainsString('secret-other-session-id', $content);
});

test('storage failure while revoking reports an error and does not sign the user out', function () {
    $this->mock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isAvailable')->andReturnTrue();
        $mock->shouldReceive('revokeOtherSessions')->andThrow(new RuntimeException('Storage unavailable'));
        $mock->shouldReceive('revokeAllSessions')->andThrow(new RuntimeException('Storage unavailable'));
        $mock->shouldReceive('revoke')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->withoutExceptionHandling([RuntimeException::class]);
    $user = User::factory()->create();

    foreach ([
        route('sessions.destroy-others'),
        route('sessions.destroy-all'),
        route('sessions.destroy', 'any-fingerprint'),
    ] as $url) {
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->delete($url)
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'error')
            ->assertSessionHas(
                'inertia.flash_data.toast.message',
                __('Sessions are temporarily unavailable. Please try again.'),
            );

        $this->assertAuthenticated();
    }
});

test('current session cannot be revoked through the individual endpoint', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $this->confirmPassword($current['cookie']);

    $fingerprint = app(ManageDatabaseSessions::class)->fingerprint($user, $current['id']);

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', $fingerprint))
        ->assertNotFound();

    $this->assertDatabaseHas('sessions', ['id' => $current['id']]);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking another session signs out the original and copied cookie', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $attacker = $this->signIn($user);
    $copiedCookie = $attacker['cookie'];

    $this->asClient($attacker['cookie'])->get(route('dashboard'))->assertOk();
    $this->asClient($copiedCookie)->get(route('dashboard'))->assertOk();

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect();

    $this->asClient($attacker['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($copiedCookie)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking other sessions signs out every other client but keeps the caller', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $others = [$this->signIn($user), $this->signIn($user)];
    $bystander = $this->signIn(User::factory()->create());

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy-others'))
        ->assertRedirect();

    foreach ($others as $other) {
        $this->asClient($other['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
    }

    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
    $this->asClient($bystander['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking all sessions signs out every client including the caller', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $other = $this->signIn($user);
    $bystander = $this->signIn(User::factory()->create());

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy-all'))
        ->assertRedirect(route('home'));

    $this->asClient($current['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($other['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($bystander['cookie'])->get(route('dashboard'))->assertOk();
});

test('a revoked client cannot revoke anything', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $attacker = $this->signIn($user);

    $this->confirmPassword($attacker['cookie']);
    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])->delete(route('sessions.destroy-others'))->assertRedirect();

    $this->asClient($attacker['cookie'])
        ->delete(route('sessions.destroy-all'))
        ->assertRedirect(route('login'));

    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking other sessions also invalidates other remember me cookies', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $attacker = $this->signInRemembered($user);
    $originalToken = $user->fresh()->remember_token;

    $this->asClient(null, $attacker['remember'])->get(route('dashboard'))->assertOk();

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])->delete(route('sessions.destroy-others'))->assertRedirect();

    $this->asClient($attacker['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient(null, $attacker['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking other sessions invalidates remember me cookies even when no other session is stored', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $attacker = $this->signInRemembered($user);
    DB::table('sessions')->where('id', $attacker['id'])->delete();

    expect(DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $current['id'])->count())->toBe(0);

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])->delete(route('sessions.destroy-others'))->assertRedirect();

    $this->asClient(null, $attacker['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking one session also ends remember me logins so it cannot come back', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $attacker = $this->signInRemembered($user);
    $originalToken = $user->fresh()->remember_token;

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect();

    $this->asClient($attacker['cookie'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient(null, $attacker['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('revoking an unknown or foreign session does not touch the remember token', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $current = $this->signIn($user);
    $this->storeSession('foreign-session', $otherUser);
    $originalToken = $user->fresh()->remember_token;
    $otherToken = $otherUser->fresh()->remember_token;
    $this->confirmPassword($current['cookie']);

    foreach (['unknown', app(ManageDatabaseSessions::class)->fingerprint($otherUser, 'foreign-session')] as $fingerprint) {
        $this->asClient($current['cookie'])->delete(route('sessions.destroy', $fingerprint))->assertNotFound();
    }

    expect($user->fresh()->remember_token)->toBe($originalToken);
    expect($otherUser->fresh()->remember_token)->toBe($otherToken);
});

test('failing to reset the remember token keeps the session when revoking one', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $this->storeSession('other-session', $user);
    $this->confirmPassword($current['cookie']);

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('resetRememberToken')->andThrow(new RuntimeException('Users unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, 'other-session')))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('a failing delete while revoking one session changes nothing', function () {
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSession')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Sessions are temporarily unavailable. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id']]);
    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a failing token update rolls back the deleted session when revoking one', function () {
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    User::updating(function (): bool {
        throw new RuntimeException('Users unavailable');
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Sessions are temporarily unavailable. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id'], 'user_id' => $user->id]);
    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a declined token update rolls back the deleted session when revoking one', function () {
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    User::updating(fn (): bool => false);

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Sessions are temporarily unavailable. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id'], 'user_id' => $user->id]);
    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a session that vanishes before deletion does not reset the remember token', function () {
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSession')->andReturn(0);
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertNotFound();

    expect($user->fresh()->remember_token)->toBe($originalToken);
});

test('a failing delete while revoking one session reports a partial result on a separate connection', function () {
    $this->useSeparateSessionConnection();
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSession')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Remember me sign-ins were ended, but sessions could not be removed. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id']]);
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient(null, $attacker['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a session that vanishes before deletion is reported as partial on a separate connection', function () {
    $this->useSeparateSessionConnection();
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSession')->andReturn(0);
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $attacker['id'])))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Remember me sign-ins were ended, but sessions could not be removed. Please try again.'));

    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient(null, $attacker['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
});

test('revocation works with sessions stored on an independent database connection', function () {
    $this->useSqliteSessionConnection();
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $first = $this->signInRemembered($user);
    $second = $this->signInRemembered($user);
    $this->confirmPassword($current['cookie']);
    $sessions = DB::connection('session_sqlite');

    $this->assertNotSame(DB::connection()->getName(), $sessions->getName());
    $this->assertNotSame(DB::connection()->getPdo(), $sessions->getPdo());
    expect($sessions->table('sessions')->where('id', $first['id'])->exists())->toBeTrue();
    expect(DB::table('sessions')->where('id', $first['id'])->exists())->toBeFalse();

    $originalToken = $user->fresh()->remember_token;
    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy', app(ManageDatabaseSessions::class)->fingerprint($user, $first['id'])))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect($sessions->table('sessions')->where('id', $first['id'])->exists())->toBeFalse();
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient(null, $first['remember'])->get(route('dashboard'))->assertRedirect(route('login'));

    $this->asClient($current['cookie'])->delete(route('sessions.destroy-others'))->assertRedirect();

    expect($sessions->table('sessions')->where('user_id', $user->id)->count())->toBe(1);
    $this->asClient(null, $second['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('a failing delete while revoking other sessions changes nothing', function () {
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSessions')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy-others'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Sessions are temporarily unavailable. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id']]);
    expect($user->fresh()->remember_token)->toBe($originalToken);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('a failing delete while revoking other sessions reports a partial result on a separate connection', function () {
    $this->useSeparateSessionConnection();
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSessions')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy-others'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Remember me sign-ins were ended, but sessions could not be removed. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id']]);
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('a failing delete while revoking all sessions changes nothing and keeps the user signed in', function () {
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSessions')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy-all'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Sessions are temporarily unavailable. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id']]);
    $this->assertDatabaseHas('sessions', ['id' => $current['id']]);
    expect($user->fresh()->remember_token)->toBe($originalToken);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('a failing delete while revoking all sessions reports a partial result on a separate connection', function () {
    $this->useSeparateSessionConnection();
    [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
    $originalToken = $user->fresh()->remember_token;

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('deleteSessions')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $this->asClient($current['cookie'])
        ->delete(route('sessions.destroy-all'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error')
        ->assertSessionHas('inertia.flash_data.toast.message', __('Remember me sign-ins were ended, but sessions could not be removed. Please try again.'));

    $this->assertDatabaseHas('sessions', ['id' => $attacker['id']]);
    $this->assertDatabaseHas('sessions', ['id' => $current['id']]);
    $this->assertNotSame($originalToken, $user->fresh()->remember_token);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});

test('a failing token update rolls back the deleted sessions when revoking others and all', function () {
    foreach (['sessions.destroy-others', 'sessions.destroy-all'] as $route) {
        [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
        $originalToken = $user->fresh()->remember_token;

        User::updating(function (): bool {
            throw new RuntimeException('Users unavailable');
        });

        $this->asClient($current['cookie'])
            ->delete(route($route))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');

        $this->assertDatabaseHas('sessions', ['id' => $attacker['id'], 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => $current['id'], 'user_id' => $user->id]);
        expect($user->fresh()->remember_token)->toBe($originalToken);

        User::flushEventListeners();
    }
});

test('a declined token update rolls back the deleted sessions when revoking others and all', function () {
    foreach (['sessions.destroy-others', 'sessions.destroy-all'] as $route) {
        [$user, $current, $attacker] = $this->userWithRememberedSecondSession();
        $originalToken = $user->fresh()->remember_token;

        User::updating(fn (): bool => false);

        $this->asClient($current['cookie'])
            ->delete(route($route))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');

        $this->assertDatabaseHas('sessions', ['id' => $attacker['id'], 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => $current['id'], 'user_id' => $user->id]);
        expect($user->fresh()->remember_token)->toBe($originalToken);

        User::flushEventListeners();
    }
});

test('revoking all sessions also invalidates remember me cookies', function () {
    $user = User::factory()->create();
    $current = $this->signInRemembered($user);
    $other = $this->signInRemembered($user);
    $bystander = $this->signInRemembered(User::factory()->create());

    $this->confirmPassword($current['cookie']);
    $this->asClient($current['cookie'], $current['remember'])
        ->delete(route('sessions.destroy-all'))
        ->assertRedirect(route('home'));

    foreach ([$current, $other] as $client) {
        $this->asClient(null, $client['remember'])->get(route('dashboard'))->assertRedirect(route('login'));
    }

    $this->asClient(null, $bystander['remember'])->get(route('dashboard'))->assertOk();
});

test('failing to reset the remember token does not report success or sign the user out', function () {
    $user = User::factory()->create();
    $current = $this->signIn($user);
    $this->storeSession('other-session', $user);
    $this->confirmPassword($current['cookie']);

    $this->partialMock(ManageDatabaseSessions::class, function (MockInterface $mock): void {
        $mock->shouldReceive('resetRememberToken')->andThrow(new RuntimeException('Users unavailable'));
    });

    foreach (['sessions.destroy-others', 'sessions.destroy-all'] as $route) {
        $this->asClient($current['cookie'])
            ->delete(route($route))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');
    }

    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    $this->asClient($current['cookie'])->get(route('dashboard'))->assertOk();
});
