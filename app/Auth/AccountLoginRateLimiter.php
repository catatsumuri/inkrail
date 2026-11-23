<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Fortify's login limiter, keyed per account instead of per submitted value.
 *
 * @see LoginThrottleKey
 */
class AccountLoginRateLimiter extends LoginRateLimiter
{
    protected function throttleKey(Request $request)
    {
        return LoginThrottleKey::for($request);
    }
}
