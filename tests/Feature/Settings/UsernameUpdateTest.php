<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Support\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UsernameUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_without_a_username_can_set_one_with_its_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'Fresh.Name',
            'email' => $user->email,
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('fresh.name', $user->fresh()->username);
    }

    public function test_changing_the_username_requires_the_current_password(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'bob',
            'email' => $user->email,
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'bob',
            'email' => $user->email,
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame('alice', $user->fresh()->username);
    }

    public function test_a_wrong_password_is_recorded_as_a_failed_username_change(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'bob',
            'email' => $user->email,
            'current_password' => 'wrong-password',
        ]);

        $event = Activity::inLog('security')->sole();

        $this->assertSame('username.change_failed', $event->description);
        $this->assertSame('failure', $event->getProperty('result'));
        $this->assertTrue($event->subject->is($user));
        $this->assertStringNotContainsString('bob', json_encode($event->properties));
    }

    public function test_changing_the_username_is_recorded_and_notified_once(): void
    {
        Notification::fake();
        $user = User::factory()->withUsername('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'bob',
            'email' => $user->email,
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $event = Activity::inLog('security')->sole();

        $this->assertSame('username.changed', $event->description);
        $this->assertTrue($event->subject->is($user));
        $this->assertStringNotContainsString('alice', json_encode($event->properties));
        $this->assertStringNotContainsString('bob', json_encode($event->properties));

        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels) => $notification->title === SecurityEvent::UsernameChanged->subject()
                && $channels === ['mail'],
        );
    }

    public function test_updating_only_the_name_does_not_touch_the_username(): void
    {
        Notification::fake();
        $user = User::factory()->withUsername('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Another Name',
            'username' => 'alice',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Another Name', $user->fresh()->name);
        $this->assertSame('alice', $user->fresh()->username);
        $this->assertSame(0, Activity::inLog('security')->count());
        Notification::assertNothingSent();
    }

    public function test_a_username_cannot_be_taken_from_another_account(): void
    {
        User::factory()->withUsername('taken')->create();
        $user = User::factory()->withUsername('alice')->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'TAKEN',
            'email' => $user->email,
            'current_password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertSame('alice', $user->fresh()->username);
    }

    public function test_a_username_cannot_look_like_an_email_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => 'someone@example.com',
            'email' => $user->email,
            'current_password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertNull($user->fresh()->username);
    }
}
