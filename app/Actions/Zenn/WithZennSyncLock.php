<?php

namespace App\Actions\Zenn;

use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use LogicException;

class WithZennSyncLock
{
    private const LEASE_SECONDS = 120;

    private const REFRESH_INTERVAL_SECONDS = 30;

    /**
     * @template TReturn
     *
     * @param  Closure(Closure(): void): TReturn  $callback
     * @return TReturn
     */
    public function __invoke(Closure $callback): mixed
    {
        $repository = config('services.github_zenn.repository');
        $branch = config('services.github_zenn.branch');
        $key = hash('sha256', serialize([is_string($repository) ? strtolower($repository) : null, $branch]));
        $lock = Cache::lock('zenn-sync:'.$key, self::LEASE_SECONDS);
        if (! $lock instanceof Lock) {
            throw new LogicException('Zenn sync requires a cache lock supporting ownership checks and renewal.');
        }
        $lock->block(0);
        $refreshAt = now()->getTimestamp() + self::REFRESH_INTERVAL_SECONDS;

        $checkLock = function () use ($lock, &$refreshAt): void {
            if (! $lock->isOwnedByCurrentProcess()) {
                throw new LockTimeoutException('The Zenn sync lock was lost.');
            }
            if (now()->getTimestamp() >= $refreshAt) {
                if (! $lock->refresh(self::LEASE_SECONDS)) {
                    throw new LockTimeoutException('The Zenn sync lock expired.');
                }
                $refreshAt = now()->getTimestamp() + self::REFRESH_INTERVAL_SECONDS;
            }
        };

        try {
            return $callback($checkLock);
        } finally {
            $lock->release();
        }
    }
}
