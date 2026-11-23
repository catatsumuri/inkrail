<?php

namespace Tests\Unit;

use App\Providers\FeatureFlagServiceProvider;
use Laravel\Fortify\Features;
use Tests\TestCase;

class FeatureFlagServiceProviderTest extends TestCase
{
    public function test_registration_is_removed_from_fortify_when_flag_is_off(): void
    {
        config(['fortify.features' => [Features::registration(), Features::resetPasswords()]]);
        config(['features.registration' => false]);

        (new FeatureFlagServiceProvider($this->app))->register();

        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertTrue(Features::enabled(Features::resetPasswords()));
    }

    public function test_fortify_features_are_left_alone_when_flag_is_on(): void
    {
        config(['fortify.features' => [Features::registration(), Features::resetPasswords()]]);
        config(['features.registration' => true]);

        (new FeatureFlagServiceProvider($this->app))->register();

        $this->assertTrue(Features::enabled(Features::registration()));
        $this->assertTrue(Features::enabled(Features::resetPasswords()));
    }

    public function test_flags_are_ignored_while_generating_frontend_routes(): void
    {
        $argv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'wayfinder:generate'];

        try {
            config(['fortify.features' => [Features::registration(), Features::passkeys()]]);
            config(['features.registration' => false, 'features.passkeys' => false]);

            (new FeatureFlagServiceProvider($this->app))->register();

            $this->assertTrue(Features::enabled(Features::registration()));
            $this->assertTrue(Features::enabled(Features::passkeys()));
        } finally {
            $_SERVER['argv'] = $argv;
        }
    }

    public function test_passkeys_are_removed_independently_of_registration(): void
    {
        config(['fortify.features' => [Features::registration(), Features::passkeys()]]);
        config(['features.registration' => true, 'features.passkeys' => false]);

        (new FeatureFlagServiceProvider($this->app))->register();

        $this->assertTrue(Features::enabled(Features::registration()));
        $this->assertFalse(Features::enabled(Features::passkeys()));
    }
}
