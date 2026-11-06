<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

use function Illuminate\Support\defer;

class SendGeneralNotification
{
    /**
     * Notify a registered user.
     *
     * @param  array<int, string>  $channels
     * @param  bool  $deferred  Send after the response, reporting instead of failing the caller on
     *                          delivery failure. Use this for channels (like mail) that can fail or
     *                          block mid-request; the default stays synchronous for callers that
     *                          need the notification to exist immediately after this call returns.
     */
    public function handle(
        User $user,
        string $title,
        string $body,
        ?string $actionUrl = null,
        array $channels = ['database'],
        bool $deferred = false,
    ): void {
        if (! $this->isValidActionUrl($actionUrl)) {
            throw new InvalidArgumentException(
                'The URL must be an application-relative path or a valid HTTPS URL.',
            );
        }

        $send = fn () => $user->notify(new GeneralNotification($title, $body, $actionUrl, $channels));

        $deferred ? $this->deliver($send) : $send();
    }

    /**
     * Notify a bare email address, after the response is sent, without ever failing the caller.
     *
     * For recipients with no user account to notify through, such as an email change's
     * previous address or a deleted account's last known address.
     */
    public function toEmailAddress(string $email, string $title, string $body, ?string $actionUrl = null): void
    {
        if (! $this->isValidActionUrl($actionUrl)) {
            throw new InvalidArgumentException(
                'The URL must be an application-relative path or a valid HTTPS URL.',
            );
        }

        $this->deliver(fn () => Notification::route('mail', $email)->notify(
            new GeneralNotification($title, $body, $actionUrl, ['mail']),
        ));
    }

    /**
     * Send a notification to the shared alert mailbox, and to Slack only when asked.
     *
     * @param  array<int, string>  $channels  Subset of `mail` and `slack`. Slack is opt-in.
     * @return array<int, string> The channels the notification was actually sent to.
     */
    public function toAlertRecipients(
        string $title,
        string $body,
        ?string $actionUrl = null,
        array $channels = ['mail'],
    ): array {
        if (! $this->isValidActionUrl($actionUrl)) {
            throw new InvalidArgumentException(
                'The URL must be an application-relative path or a valid HTTPS URL.',
            );
        }

        $routes = array_filter([
            'mail' => config('services.alerts.mail_to'),
            'slack' => config('services.alerts.slack_webhook_url'),
        ]);

        $deliverable = array_values(array_intersect($channels, array_keys($routes)));

        if ($deliverable === []) {
            return [];
        }

        $notifiable = Notification::routes(array_intersect_key($routes, array_flip($deliverable)));

        $notifiable->notify(new GeneralNotification($title, $body, $actionUrl, $deliverable));

        return $deliverable;
    }

    /**
     * Run the delivery after the response, reporting instead of throwing on failure.
     */
    private function deliver(callable $send): void
    {
        defer(function () use ($send): void {
            try {
                $send();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    protected function isValidActionUrl(?string $actionUrl): bool
    {
        if ($actionUrl === null) {
            return true;
        }

        if (str_starts_with($actionUrl, '/') && ! str_starts_with($actionUrl, '//')) {
            return true;
        }

        return filter_var($actionUrl, FILTER_VALIDATE_URL) !== false
            && parse_url($actionUrl, PHP_URL_SCHEME) === 'https';
    }
}
