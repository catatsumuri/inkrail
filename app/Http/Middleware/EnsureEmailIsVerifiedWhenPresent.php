<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

/**
 * The "verified" middleware for accounts that may have no email address.
 *
 * An account without an email address has nothing to verify, so it passes.
 * Everyone else is checked exactly as Laravel does. hasVerifiedEmail() is
 * deliberately left alone: the security log gate calls it directly and must
 * keep treating an account without an address as unverified.
 */
class EnsureEmailIsVerifiedWhenPresent extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        $user = $request->user();

        if ($user instanceof User && $user->email === null) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
