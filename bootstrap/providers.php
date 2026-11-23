<?php

use App\Providers\AppServiceProvider;
use App\Providers\FeatureFlagServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SecurityNotificationServiceProvider;
use App\Providers\UsernameLoginServiceProvider;

return [
    AppServiceProvider::class,
    FeatureFlagServiceProvider::class,
    FortifyServiceProvider::class,
    SecurityNotificationServiceProvider::class,
    UsernameLoginServiceProvider::class,
];
