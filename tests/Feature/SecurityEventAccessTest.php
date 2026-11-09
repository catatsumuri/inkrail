<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Tests\Concerns\SecurityEventAccessHelpers;

uses(RefreshDatabase::class);

uses(SecurityEventAccessHelpers::class);

test('guests are redirected to the login page', function () {
    $this->get(route('admin.security-events.index'))->assertRedirect(route('login'));
});

test('a user who is not a viewer is forbidden', function () {
    config(['audit.security_viewers' => ['viewer@example.com']]);

    $this->actingAs(User::factory()->create(['email' => 'other@example.com']))
        ->getJson(route('admin.security-events.index'))
        ->assertForbidden();
});

test('nobody can view when no viewers are configured', function () {
    config(['audit.security_viewers' => []]);

    $this->actingAs(User::factory()->create(['email' => 'viewer@example.com']))
        ->getJson(route('admin.security-events.index'))
        ->assertForbidden();
});

test('a viewer sees only security events newest first', function () {
    $viewer = $this->verifiedViewer();
    $this->recordEvent('security', 'password.changed', 'success');
    $this->recordEvent('other', 'note', 'success');
    $this->recordEvent('security', 'login.failed', 'failure');

    $this->actingAs($viewer)
        ->getJson(route('admin.security-events.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'login.failed')
        ->assertJsonPath('data.0.result', 'failure')
        ->assertJsonPath('data.1.event', 'password.changed');
});

test('the viewer email is matched case insensitively', function () {
    $viewer = $this->verifiedViewer('Viewer@Example.com');

    $this->actingAs($viewer)
        ->getJson(route('admin.security-events.index'))
        ->assertOk();
});

test('a viewer with an unverified email is redirected to verification', function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
    config(['audit.security_viewers' => ['viewer@example.com']]);

    $this->actingAs(User::factory()->unverified()->create(['email' => 'viewer@example.com']))
        ->get(route('admin.security-events.index'))
        ->assertRedirect(route('verification.notice'));
});

test('events can be filtered by result', function () {
    $viewer = $this->verifiedViewer();
    $this->recordEvent('security', 'password.changed', 'success');
    $this->recordEvent('security', 'login.failed', 'failure');

    $this->actingAs($viewer)
        ->getJson(route('admin.security-events.index', ['result' => 'failure']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'login.failed');
});

test('events can be filtered by event name', function () {
    $viewer = $this->verifiedViewer();
    $this->recordEvent('security', 'password.changed', 'success');
    $this->recordEvent('security', 'login.failed', 'failure');

    $this->actingAs($viewer)
        ->getJson(route('admin.security-events.index', ['event' => 'password.changed']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'password.changed');
});

test('security events cannot be changed over http', function () {
    $viewer = $this->verifiedViewer();

    $this->actingAs($viewer)->putJson('/admin/security-events')->assertStatus(405);
    $this->actingAs($viewer)->deleteJson('/admin/security-events')->assertStatus(405);
    $this->actingAs($viewer)->postJson('/admin/security-events')->assertStatus(405);
});

test('the gate denies a listed email that is not verified', function () {
    config(['audit.security_viewers' => ['viewer@example.com']]);

    $unverified = User::factory()->unverified()->create(['email' => 'viewer@example.com']);

    expect(Gate::forUser($unverified)->denies('view-security-events'))->toBeTrue();
});

test('the gate allows a listed email that is verified', function () {
    $viewer = $this->verifiedViewer();

    expect(Gate::forUser($viewer)->allows('view-security-events'))->toBeTrue();
});

test('a listed email cannot view when the user has no email verification', function () {
    config(['audit.security_viewers' => ['viewer@example.com']]);

    $this->actingAs($this->userWithoutEmailVerification('viewer@example.com'))
        ->getJson(route('admin.security-events.index'))
        ->assertForbidden();
});

test('the gate denies a listed email when the user has no email verification', function () {
    config(['audit.security_viewers' => ['viewer@example.com']]);

    $user = $this->userWithoutEmailVerification('viewer@example.com');

    expect(Gate::forUser($user)->denies('view-security-events'))->toBeTrue();
});
