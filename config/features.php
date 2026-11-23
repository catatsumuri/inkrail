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

];
