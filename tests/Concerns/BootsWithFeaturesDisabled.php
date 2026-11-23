<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

/**
 * Boots the application with the given config/features.php flags turned off.
 *
 * Feature flags are evaluated while the application boots (Fortify decides
 * which routes exist at that point), so changing config() inside a test
 * cannot remove routes that are already registered. The flags are applied
 * right after configuration loads and before service providers register.
 *
 * Declare `protected array $disabledFeatures = ['passkeys'];` on the test.
 */
trait BootsWithFeaturesDisabled
{
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->afterBootstrapping(
            LoadConfiguration::class,
            function ($app) {
                foreach ($this->disabledFeatures as $flag) {
                    $app['config']->set("features.{$flag}", false);
                }
            },
        );

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
