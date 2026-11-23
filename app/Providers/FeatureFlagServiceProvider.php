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
        // The frontend imports generated route helpers for every feature, so
        // the generated files must describe the full route set whatever the
        // flags say. HTTP requests still honour the flags.
        if ($this->isGeneratingFrontendRoutes()) {
            return;
        }

        $fortifyFeatures = [
            'registration' => Features::registration(),
            'passkeys' => Features::passkeys(),
        ];

        foreach ($fortifyFeatures as $flag => $feature) {
            if (! config("features.{$flag}", true)) {
                $this->disableFortifyFeature($feature);
            }
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

    private function isGeneratingFrontendRoutes(): bool
    {
        return $this->app->runningInConsole()
            && ($_SERVER['argv'][1] ?? null) === 'wayfinder:generate';
    }
}
