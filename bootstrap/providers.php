<?php

use App\Providers\AppServiceProvider;
use App\Providers\FeatureFlagServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SecurityNotificationServiceProvider;

return [
    AppServiceProvider::class,
    FeatureFlagServiceProvider::class,
    FortifyServiceProvider::class,
    SecurityNotificationServiceProvider::class,
];
