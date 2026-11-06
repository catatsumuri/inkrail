<?php

namespace App\Providers;

use App\Actions\Notifications\SendGeneralNotification;
use App\Models\User;
use App\Support\SecurityEvent;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
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
    private SendGeneralNotification $sendGeneralNotification;

    /**
     * Bootstrap any application services.
     */
    public function boot(SendGeneralNotification $sendGeneralNotification): void
    {
        $this->sendGeneralNotification = $sendGeneralNotification;

        $this->listenFor(
            PasswordReset::class,
            SecurityEvent::PasswordReset,
            // Fortify's CompletePasswordReset dispatches this unconditionally,
            // even if the password save (a separate, earlier action) was
            // cancelled, so check that the hash it set is what's persisted.
            fn (object $event): bool => $event instanceof PasswordReset
                && ($user = $this->eventUser($event)) !== null
                && $user->password === $this->freshUser($event)?->password,
        );
        $this->listenFor(
            RecoveryCodesGenerated::class,
            SecurityEvent::RecoveryCodesRegenerated,
            // Fortify dispatches this without checking that the save
            // succeeded either.
            fn (object $event): bool => $event instanceof RecoveryCodesGenerated
                && $event->user->two_factor_recovery_codes !== null
                && $event->user->two_factor_recovery_codes === $this->freshUser($event)?->two_factor_recovery_codes,
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

        // Fortify's 2FA domain events (Confirmed/Disabled/Enabled) are
        // dispatched unconditionally, even when the save they follow was
        // cancelled or changed nothing, and distinguishing a genuine
        // enable/disable transition from a redundant resubmit needs the
        // pre-save state. Eloquent's own "updated" event already carries
        // both properties for free: it only fires once a save actually
        // persisted a real change, and getOriginal() at that point still
        // holds the value from before this save (syncOriginal() runs after,
        // not before). So 2FA notifications are driven directly from here
        // instead of from Fortify's events, with no need to remember
        // anything between requests or between model instances.
        User::updated(function (User $user): void {
            $this->notifyOnTwoFactorTransition($user);
        });
    }

    /**
     * Notify the user when this save just transitioned two-factor
     * authentication between active and inactive. "Active" means confirmed
     * when confirmation is required (that mode never sets
     * two_factor_confirmed_at otherwise, including for an unconfirmed,
     * cancelled setup), or just having a secret when it isn't.
     */
    private function notifyOnTwoFactorTransition(User $user): void
    {
        $confirmRequired = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');

        $wasActive = $confirmRequired
            ? $user->getOriginal('two_factor_confirmed_at') !== null
            : $user->getOriginal('two_factor_secret') !== null;

        $isActive = $confirmRequired
            ? $user->two_factor_confirmed_at !== null
            : $user->two_factor_secret !== null;

        if ($wasActive === $isActive) {
            return;
        }

        $securityEvent = $isActive ? SecurityEvent::TwoFactorEnabled : SecurityEvent::TwoFactorDisabled;

        $this->sendGeneralNotification->handle(
            $user,
            $securityEvent->subject(),
            $securityEvent->mailBody(),
            channels: ['mail'],
            deferred: true,
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
                $this->sendGeneralNotification->handle(
                    $user,
                    $securityEvent->subject(),
                    $securityEvent->mailBody(),
                    channels: ['mail'],
                    deferred: true,
                );
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
            $event instanceof PasskeyRegistered,
            $event instanceof PasskeyDeleted => $event->user,
            default => null,
        };

        return $user instanceof User ? $user : null;
    }
}
