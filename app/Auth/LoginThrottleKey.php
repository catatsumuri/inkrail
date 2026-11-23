<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Builds the key that limits failed sign-in attempts.
 *
 * Both identifiers of an account (username and email address) must share one
 * allowance, otherwise an attacker doubles the guesses by alternating them.
 * When the submitted value resolves to an account the key is that account;
 * otherwise it is the submitted value. The client IP is always part of the key,
 * so a third party cannot lock an account out from another address.
 *
 * An unknown value and a known one both get the same limit, so the limiter does
 * not reveal whether an account exists.
 */
final class LoginThrottleKey
{
    public static function for(Request $request): string
    {
        $login = Str::lower((string) $request->input(Fortify::username()));

        $user = $login === ''
            ? null
            : Auth::createUserProvider(config('auth.guards.'.config('fortify.guard').'.provider'))
                ?->retrieveByCredentials([UsernameOrEmailUserProvider::LOGIN_KEY => $login]);

        $subject = $user !== null ? 'account:'.$user->getAuthIdentifier() : 'login:'.$login;

        return Str::transliterate($subject.'|'.$request->ip());
    }
}
