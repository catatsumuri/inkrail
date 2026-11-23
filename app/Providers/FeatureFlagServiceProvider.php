<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Features;

/**
 * Applies config/features.php on top of config/fortify.php.
 *
 * Fortify decides which routes exist from its `features` list while booting,
 * so the list is adjusted here, during register(), before that happens. This
 * keeps config/fortify.php untouched and free of merge conflicts with upstream.
 */
class FeatureFlagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('features.registration', true)) {
            $this->disableFortifyFeature(Features::registration());
        }
    }

    private function disableFortifyFeature(string $feature): void
    {
        config([
            'fortify.features' => array_values(array_filter(
                config('fortify.features', []),
                fn ($entry) => $entry !== $feature,
            )),
        ]);
    }
}
