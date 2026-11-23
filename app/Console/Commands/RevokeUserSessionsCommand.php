<?php

namespace App\Console\Commands;

use App\Actions\Security\ResolveSecurityUser;
use App\Actions\Sessions\ManageDatabaseSessions;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class RevokeUserSessionsCommand extends Command
{
    /** @var string */
    protected $signature = 'security:revoke-sessions
        {selector : User selector: id:123, email:user@example.com or username:alice}
        {--force : Revoke sessions without interactive confirmation}';

    /** @var string */
    protected $description = 'Revoke every database session belonging to a user';

    public function handle(ResolveSecurityUser $users, ManageDatabaseSessions $sessions): int
    {
        $user = $this->resolveUser($users);

        if (! $user instanceof User) {
            return self::FAILURE;
        }

        if (! $sessions->isAvailable()) {
            $this->error('Session revocation requires the database session driver.');

            return self::FAILURE;
        }

        try {
            $storedSessionCount = $sessions->storedSessionCount($user);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Session storage is unavailable. No sessions were revoked.');

            return self::FAILURE;
        }

        $subject = $this->describe($user);
        $message = $storedSessionCount === 0
            ? "Revoke 0 stored sessions and reset remember me logins for {$subject}?"
            : "Revoke {$storedSessionCount} session(s) for {$subject}?";

        if (! $this->option('force') && ! $this->confirm($message, false)) {
            $this->warn('No sessions were revoked.');

            return self::SUCCESS;
        }

        if ($storedSessionCount === 0) {
            return $this->resetRememberMeOnly($sessions, $user);
        }

        try {
            $sessions->resetRememberToken($user);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Remember me logins could not be reset. No sessions were revoked.');

            return self::FAILURE;
        }

        try {
            $revokedCount = $sessions->revokeAllSessions($user);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Remember me logins were reset, but session storage is unavailable. No sessions were revoked.');

            return self::FAILURE;
        }

        $this->info("Revoked {$revokedCount} session(s) for user ID {$user->getKey()}.");

        return self::SUCCESS;
    }

    private function resetRememberMeOnly(ManageDatabaseSessions $sessions, User $user): int
    {
        try {
            $sessions->resetRememberToken($user);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Remember me logins could not be reset. No sessions were revoked.');

            return self::FAILURE;
        }

        $this->info('No sessions are stored for '.$this->describe($user).'. Remember me logins were reset.');

        return self::SUCCESS;
    }

    /**
     * Name the user the way operators see them: by email, or by username when there is none.
     */
    private function describe(User $user): string
    {
        $identifier = $user->email ?? $user->username;

        return "{$user->name} ({$identifier}, ID {$user->getKey()})";
    }

    private function resolveUser(ResolveSecurityUser $users): ?User
    {
        $selector = $this->argument('selector');

        try {
            $user = $users->handle($selector);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return null;
        }

        if ($user === null) {
            $this->error('The specified user was not found.');
        }

        return $user;
    }
}
