<?php

/*
|--------------------------------------------------------------------------
| Feature Flags
|--------------------------------------------------------------------------
|
| Runtime switches for starter-kit features. They replace the compile-time
| selection that Chisel used to perform: the code stays in place and only the
| behaviour is turned off. See App\Providers\FeatureFlagServiceProvider.
|
| These are deployment-time flags, not per-request switches. They are read when
| the application boots (Fortify decides which routes exist at that point), so
| after changing one you must regenerate the config cache and restart any
| long-lived workers (Octane, queue workers, etc.).
|
| A flag can only disable a feature that is still present in the codebase. It
| cannot restore a feature that Chisel has already removed.
|
*/

return [

    // Public self-service registration (Fortify's register routes).
    'registration' => (bool) env('FEATURE_REGISTRATION', true),

    // Passkey (WebAuthn) sign-in and management, including the
    // /.well-known/passkey-endpoints discovery route.
    'passkeys' => (bool) env('FEATURE_PASSKEYS', true),

    // Two-factor authentication: setup UI, recovery codes and the login
    // challenge.
    //
    // WARNING: turning this off also removes the login challenge. Accounts
    // that already enrolled in two-factor authentication can then sign in
    // with their password alone. Only disable it deliberately.
    'two_factor' => (bool) env('FEATURE_TWO_FACTOR', true),

    // Re-entering the password before sensitive settings (security page,
    // enabling two-factor or passkeys). When off, none of those screens ask
    // for it, and the browser session-management pages are removed too, since
    // revoking sessions relies on a recent password confirmation.
    'password_confirmation' => (bool) env('FEATURE_PASSWORD_CONFIRMATION', true),

];
