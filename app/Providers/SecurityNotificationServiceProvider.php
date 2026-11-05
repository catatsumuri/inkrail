<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SecurityEvent;
use App\Support\SecurityNotifier;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEvent;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Notifies users by email when sensitive security events succeed.
 *
 * Notifications are sent from the code path of the action itself, never derived
 * from an audit log, and a failed notification never fails the action.
 */
class SecurityNotificationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->listenFor(PasswordReset::class, SecurityEvent::PasswordReset);
        $this->listenFor(RecoveryCodesGenerated::class, SecurityEvent::RecoveryCodesRegenerated);

        // Fortify and the passkeys package fire these events even when the
        // underlying save was cancelled, so confirm the persisted state first.
        $this->listenFor(
            TwoFactorAuthenticationConfirmed::class,
            SecurityEvent::TwoFactorEnabled,
            fn (object $event): bool => $event instanceof TwoFactorAuthenticationConfirmed
                && $this->freshUser($event)?->two_factor_confirmed_at !== null,
        );
        $this->listenFor(
            TwoFactorAuthenticationDisabled::class,
            SecurityEvent::TwoFactorDisabled,
            fn (object $event): bool => $event instanceof TwoFactorAuthenticationDisabled
                && $this->freshUser($event)?->two_factor_secret === null,
        );
        $this->listenFor(
            PasskeyRegistered::class,
            SecurityEvent::PasskeyAdded,
            fn (object $event): bool => $event instanceof PasskeyRegistered && $event->passkey->exists,
        );
        $this->listenFor(
            PasskeyDeleted::class,
            SecurityEvent::PasskeyRemoved,
            fn (object $event): bool => $event instanceof PasskeyDeleted
                && ! $event->passkey->newQuery()->whereKey($event->passkey->getKey())->exists(),
        );

        // With confirmation required, "enabled" only creates the secret; the
        // change takes effect on "confirmed".
        $this->listenFor(
            TwoFactorAuthenticationEnabled::class,
            SecurityEvent::TwoFactorEnabled,
            fn (object $event): bool => $event instanceof TwoFactorAuthenticationEnabled
                && ! Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
                && $this->freshUser($event)?->two_factor_secret !== null,
        );
    }

    /**
     * Send the given security notification when the event fires for a user.
     *
     * @param  class-string  $eventClass
     * @param  (callable(object): bool)|null  $when
     */
    private function listenFor(string $eventClass, SecurityEvent $securityEvent, ?callable $when = null): void
    {
        Event::listen($eventClass, function (object $event) use ($securityEvent, $when): void {
            $user = $this->eventUser($event);

            if ($user instanceof User && ($when === null || $when($event))) {
                SecurityNotifier::notify($user, $securityEvent);
            }
        });
    }

    /**
     * Get the event's user as currently persisted.
     */
    private function freshUser(object $event): ?User
    {
        return $this->eventUser($event)?->fresh();
    }

    /**
     * Get the user carried by one of the security events this provider listens for.
     */
    private function eventUser(object $event): ?User
    {
        $user = match (true) {
            $event instanceof PasswordReset,
            $event instanceof RecoveryCodesGenerated,
            $event instanceof TwoFactorAuthenticationEvent,
            $event instanceof PasskeyRegistered,
            $event instanceof PasskeyDeleted => $event->user,
            default => null,
        };

        return $user instanceof User ? $user : null;
    }
}
