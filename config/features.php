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
*/

return [

    // Public self-service registration (Fortify's register routes).
    'registration' => (bool) env('FEATURE_REGISTRATION', true),

];
