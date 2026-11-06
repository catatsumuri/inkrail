<?php

namespace App\Providers;

use App\Actions\Notifications\SendGeneralNotification;
use App\Models\User;
use App\Support\SecurityEvent;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
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

        // Fortify and the passkeys package fire these events even when the
        // underlying save was cancelled or changed nothing, so confirm the
        // persisted state first. Re-confirming an already-confirmed account,
        // or cancelling a setup that was never confirmed, dispatch the same
        // events, so whether 2FA was already active is checked too, not just
        // the post-save state. That "was already active" fact is tracked in
        // the cache (see wasTwoFactorActiveBefore()), not from the model or
        // its save: two separate requests resolve two separate User
        // instances, and Eloquent skips the "updating" event entirely for a
        // same-second resubmit that re-sets an already-current value, so
        // nothing in-process can be trusted to still be there, or to have
        // fired, on the next request.
        $this->listenFor(
            TwoFactorAuthenticationConfirmed::class,
            SecurityEvent::TwoFactorEnabled,
            function (object $event): bool {
                if (! $event instanceof TwoFactorAuthenticationConfirmed
                    || ($user = $this->freshUser($event)) === null
                    || $user->two_factor_confirmed_at === null) {
                    return false;
                }

                $wasActive = $this->wasTwoFactorActiveBefore($user);
                $this->rememberTwoFactorActive($user, true);

                return ! $wasActive;
            },
        );
        $this->listenFor(
            TwoFactorAuthenticationDisabled::class,
            SecurityEvent::TwoFactorDisabled,
            function (object $event): bool {
                if (! $event instanceof TwoFactorAuthenticationDisabled
                    || ($user = $this->freshUser($event)) === null
                    || $user->two_factor_secret !== null) {
                    return false;
                }

                $wasActive = $this->wasTwoFactorActiveBefore($user);
                $this->rememberTwoFactorActive($user, false);

                return $wasActive;
            },
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
            function (object $event): bool {
                if (! $event instanceof TwoFactorAuthenticationEnabled
                    || Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
                    || ($user = $this->freshUser($event)) === null
                    || $user->two_factor_secret === null) {
                    return false;
                }

                $wasActive = $this->wasTwoFactorActiveBefore($user);
                $this->rememberTwoFactorActive($user, true);

                return ! $wasActive;
            },
        );
    }

    /**
     * Whether two-factor authentication was active the last time this
     * provider checked, per the durable record kept in the cache (not
     * per-request state, which a fresh User instance on the next request
     * would not have).
     *
     * A user never checked before (most likely: 2FA was already active
     * before this record-keeping existed) reads as inactive, so their first
     * disable after that goes unnotified; see the PR for that trade-off.
     */
    private function wasTwoFactorActiveBefore(User $user): bool
    {
        return (bool) Cache::get($this->twoFactorActiveCacheKey($user));
    }

    private function rememberTwoFactorActive(User $user, bool $active): void
    {
        Cache::put($this->twoFactorActiveCacheKey($user), $active, now()->addYear());
    }

    private function twoFactorActiveCacheKey(User $user): string
    {
        return 'security-notifications:two-factor-active:'.$user->getKey();
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
            $event instanceof TwoFactorAuthenticationEvent,
            $event instanceof PasskeyRegistered,
            $event instanceof PasskeyDeleted => $event->user,
            default => null,
        };

        return $user instanceof User ? $user : null;
    }
}
