<?php

namespace App\Providers;

use App\Auth\UsernameOrEmailUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the "eloquent-login" user provider used by config/auth.php so
 * that people can sign in with a username or an email address.
 */
class UsernameLoginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider('eloquent-login', function ($app, array $config) {
            return new UsernameOrEmailUserProvider($app['hash'], $config['model']);
        });
    }
}
