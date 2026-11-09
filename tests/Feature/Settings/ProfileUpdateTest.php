<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email cannot be changed without the current password', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'attacker@example.com',
        ])
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->email)->toBe($originalEmail);
});

test('email cannot be changed with an incorrect password', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'attacker@example.com',
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->email)->toBe($originalEmail);
});

test('current password cannot be brute forced through the profile update', function () {
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'attacker@example.com',
            'current_password' => 'wrong-password',
        ]);
    }

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'attacker@example.com',
            'current_password' => 'password',
        ])
        ->assertTooManyRequests();

    $this->assertNotSame('attacker@example.com', $user->refresh()->email);
});

test('profile update throttling does not affect other users', function () {
    $throttledUser = User::factory()->create();
    $otherUser = User::factory()->create();

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->actingAs($throttledUser)->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'attacker@example.com',
            'current_password' => 'wrong-password',
        ]);
    }

    $this
        ->actingAs($otherUser)
        ->patch(route('profile.update'), [
            'name' => 'Other User',
            'email' => 'other@example.com',
            'current_password' => 'password',
        ])
        ->assertSessionHasNoErrors();

    expect($otherUser->refresh()->email)->toBe('other@example.com');
});

test('email cannot be changed by letter case without the current password', function () {
    $user = User::factory()->create(['email' => 'Alice@example.com']);

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'alice@example.com',
        ])
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->email)->toBe('Alice@example.com');
});

test('array email is rejected with a validation error', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => ['attacker@example.com'],
            'current_password' => 'password',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe($originalEmail);
});

test('empty email is rejected with a validation error', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => '',
            'current_password' => 'password',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe($originalEmail);
});

test('array current password is rejected with a validation error', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'attacker@example.com',
            'current_password' => ['password'],
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe($originalEmail);
});

test('email is not changed by extra request fields', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'password' => 'hijacked-password',
            'email_verified_at' => null,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->email)->toBe($originalEmail);
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('name can be changed without the current password', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'New Name',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('New Name');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
