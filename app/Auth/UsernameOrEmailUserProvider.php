<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Looks a user up by username or email address.
 *
 * Fortify's login field is named "login" (config/fortify.php) and holds either
 * identifier. Resolving it here, inside the user provider, keeps the standard
 * guard flow intact: a rejected attempt for a known account still fires
 * Illuminate\Auth\Events\Failed with that user, which the security log relies on.
 *
 * Usernames cannot contain "@", so a value never matches two different accounts.
 */
class UsernameOrEmailUserProvider extends EloquentUserProvider
{
    public const LOGIN_KEY = 'login';

    /**
     * @param  array<string, mixed>  $credentials
     * @return (Authenticatable&Model)|null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (! array_key_exists(self::LOGIN_KEY, $credentials)) {
            return parent::retrieveByCredentials($credentials);
        }

        $login = $credentials[self::LOGIN_KEY];

        if (! is_string($login) || $login === '') {
            return null;
        }

        $login = Str::lower($login);

        $user = $this->newModelQuery()
            ->where(fn ($query) => $query->where('username', $login)->orWhere('email', $login))
            ->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
