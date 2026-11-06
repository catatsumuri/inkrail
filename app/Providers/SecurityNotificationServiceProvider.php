<?php

namespace App\Providers;

use App\Actions\Notifications\SendGeneralNotification;
use App\Models\User;
use App\Support\SecurityEvent;
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
use WeakMap;

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
     * Whether two-factor authentication was already active for each user
     * immediately before their current save, captured from the pre-save
     * model state. "Active" means confirmed when confirmation is required,
     * or just having a secret when it isn't (that mode never sets
     * two_factor_confirmed_at at all). Used to tell a real enable/disable
     * transition apart from a redundant re-confirm or the cancellation of a
     * setup that was never confirmed, both of which leave the post-save
     * state looking the same.
     *
     * @var WeakMap<User, bool>
     */
    private WeakMap $wasTwoFactorActiveBeforeSave;

    /**
     * Bootstrap any application services.
     */
    public function boot(SendGeneralNotification $sendGeneralNotification): void
    {
        $this->sendGeneralNotification = $sendGeneralNotification;
        $this->wasTwoFactorActiveBeforeSave = new WeakMap;

        User::updating(function (User $user): void {
            $this->wasTwoFactorActiveBeforeSave[$user] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
                ? $user->getOriginal('two_factor_confirmed_at') !== null
                : $user->getOriginal('two_factor_secret') !== null;
        });

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
        // underlying save was cancelled, so confirm the persisted state first.
        // Re-confirming an already-confirmed account, or cancelling a setup
        // that was never confirmed, dispatch the same events, so the pre-save
        // state is checked too, not just the post-save state. A resubmit that
        // doesn't actually change the timestamp (same second) skips Eloquent's
        // "updating" event entirely, so the map is also kept up to date here,
        // from the outcome of this check, not only from "updating".
        $this->listenFor(
            TwoFactorAuthenticationConfirmed::class,
            SecurityEvent::TwoFactorEnabled,
            function (object $event): bool {
                if (! $event instanceof TwoFactorAuthenticationConfirmed
                    || $this->freshUser($event)?->two_factor_confirmed_at === null
                    || ($this->wasTwoFactorActiveBeforeSave[$event->user] ?? false)) {
                    return false;
                }

                $this->wasTwoFactorActiveBeforeSave[$event->user] = true;

                return true;
            },
        );
        $this->listenFor(
            TwoFactorAuthenticationDisabled::class,
            SecurityEvent::TwoFactorDisabled,
            function (object $event): bool {
                if (! $event instanceof TwoFactorAuthenticationDisabled
                    || $this->freshUser($event)?->two_factor_secret !== null
                    || ! ($this->wasTwoFactorActiveBeforeSave[$event->user] ?? false)) {
                    return false;
                }

                $this->wasTwoFactorActiveBeforeSave[$event->user] = false;

                return true;
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
                    || $this->freshUser($event)?->two_factor_secret === null) {
                    return false;
                }

                $this->wasTwoFactorActiveBeforeSave[$event->user] = true;

                return true;
            },
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
            $event instanceof TwoFactorAuthenticationEvent,
            $event instanceof PasskeyRegistered,
            $event instanceof PasskeyDeleted => $event->user,
            default => null,
        };

        return $user instanceof User ? $user : null;
    }
}
