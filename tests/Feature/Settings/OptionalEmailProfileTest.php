<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Support\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OptionalEmailProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_without_an_email_can_add_one_with_its_password(): void
    {
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice',
            'email' => 'Alice@Example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('alice@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('email.changed', Activity::inLog('security')->sole()->description);
    }

    public function test_adding_an_email_requires_the_current_password(): void
    {
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice',
            'email' => 'alice@example.com',
        ])->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->email);
    }

    public function test_leaving_the_email_empty_needs_no_extra_password(): void
    {
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Another Name',
            'username' => 'alice',
            'email' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Another Name', $user->fresh()->name);
        $this->assertNull($user->fresh()->email);
        $this->assertSame(0, Activity::inLog('security')->count());
    }

    public function test_an_email_that_was_set_cannot_be_removed(): void
    {
        $user = User::factory()->withUsername('alice')->create(['email' => 'alice@example.com']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice',
            'email' => '',
            'current_password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertSame('alice@example.com', $user->fresh()->email);
    }

    public function test_after_adding_an_email_verification_is_required(): void
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'current_password' => 'password',
        ]);

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
        $this->actingAs($user->fresh())->get(route('articles.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_adding_a_first_email_notifies_nobody_by_mail(): void
    {
        Notification::fake();
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    // Notifications

    public function test_security_notices_for_an_account_without_an_email_stay_in_the_app(): void
    {
        Notification::fake();
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels) => $notification->title === SecurityEvent::PasswordChanged->subject()
                && $channels === ['database'],
        );
    }

    public function test_changing_the_username_of_an_account_without_an_email_notifies_in_the_app(): void
    {
        Notification::fake();
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice2',
            'email' => '',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, GeneralNotification::class, fn ($n, array $channels) => $channels === ['database']);
    }

    public function test_changing_both_username_and_email_notifies_the_previous_address(): void
    {
        Notification::fake();
        $user = User::factory()->withUsername('alice')->create(['email' => 'old@example.com']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice2',
            'email' => 'new@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notification->title === SecurityEvent::UsernameChanged->subject()
                && $notifiable->routes['mail'] === 'old@example.com',
        );
        Notification::assertNotSentTo($user, GeneralNotification::class);
    }

    public function test_changing_only_the_username_notifies_the_current_address(): void
    {
        Notification::fake();
        $user = User::factory()->withUsername('alice')->create(['email' => 'alice@example.com']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'alice2',
            'email' => 'alice@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, GeneralNotification::class, fn ($n, array $channels) => $channels === ['mail']);
    }

    public function test_deleting_an_account_without_an_email_works(): void
    {
        Notification::fake();
        $user = User::factory()->withoutEmail('alice')->create();

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Notification::assertNothingSent();
    }
}
