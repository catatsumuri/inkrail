<?php

namespace App\Providers;

use App\Auth\AccountLoginRateLimiter;
use App\Auth\UsernameOrEmailUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Registers the "eloquent-login" user provider used by config/auth.php so
 * that people can sign in with a username or an email address.
 */
class UsernameLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginRateLimiter::class, AccountLoginRateLimiter::class);
    }

    public function boot(): void
    {
        Auth::provider('eloquent-login', function ($app, array $config) {
            return new UsernameOrEmailUserProvider($app['hash'], $config['model']);
        });
    }
}
