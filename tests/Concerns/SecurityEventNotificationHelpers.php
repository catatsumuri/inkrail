<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Support\SecurityEvent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Laravel\Passkeys\Passkey;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

trait SecurityEventNotificationHelpers
{
    /**
     * @return array<string, int>
     */
    protected function passwordConfirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    protected function updatePassword(User $user, string $currentPassword)
    {
        return $this->actingAs($user)->withSession($this->passwordConfirmed())
            ->put(route('user-password.update'), [
                'current_password' => $currentPassword,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
    }

    protected function resetPassword(User $user, string $token)
    {
        return $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
    }

    protected function changeEmail(User $user, string $email, string $currentPassword)
    {
        return $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $email,
            'current_password' => $currentPassword,
        ]);
    }

    protected function createPasskey(User $user): Passkey
    {
        return $user->passkeys()->forceCreate([
            'name' => 'Laptop',
            'credential_id' => fake()->uuid(),
            'credential' => [],
        ]);
    }

    protected function currentTwoFactorCode(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    }

    protected function runDeferredCallbacks(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }

    /**
     * Assert exactly one notification was sent in total, by mail, for the given event, to the user.
     */
    protected function assertNotifiedOnce(User $user, SecurityEvent $event): void
    {
        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            GeneralNotification::class,
            fn (GeneralNotification $notification, array $channels) => $notification->title === $event->subject()
                && $notification->body === $event->mailBody()
                && $channels === ['mail'],
        );
    }

    /**
     * Make every notification delivery throw, counting how often delivery was attempted.
     */
    protected function failNotificationDelivery(): \ArrayObject
    {
        $attempts = new \ArrayObject;
        Exceptions::fake();
        Event::listen(NotificationSending::class, function () use ($attempts): void {
            $attempts[] = true;

            throw new RuntimeException('mail down');
        });

        return $attempts;
    }

    /**
     * Assert delivery was really attempted and failed, and the failure was only reported.
     *
     * @param  \ArrayObject<int, bool>  $attempts
     */
    protected function assertDeliveryFailedWithoutAffectingOperation(\ArrayObject $attempts): void
    {
        $this->assertCount(1, $attempts, 'The notification must be attempted exactly once.');
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'mail down');
    }
}
