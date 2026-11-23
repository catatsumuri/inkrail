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

    public function test_two_factor_is_removed_independently_of_other_flags(): void
    {
        config(['fortify.features' => [Features::registration(), Features::twoFactorAuthentication(), Features::passkeys()]]);
        config(['features.registration' => true, 'features.passkeys' => true, 'features.two_factor' => false]);

        (new FeatureFlagServiceProvider($this->app))->register();

        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertTrue(Features::enabled(Features::registration()));
        $this->assertTrue(Features::enabled(Features::passkeys()));
    }

    public function test_disabling_password_confirmation_clears_the_fortify_options(): void
    {
        config([
            'fortify-options.two-factor-authentication.confirmPassword' => true,
            'fortify-options.passkeys.confirmPassword' => true,
            'features.password_confirmation' => false,
        ]);

        (new FeatureFlagServiceProvider($this->app))->register();

        $this->assertFalse(config('fortify-options.two-factor-authentication.confirmPassword'));
        $this->assertFalse(config('fortify-options.passkeys.confirmPassword'));
    }

    public function test_all_flags_are_forced_on_while_generating_frontend_routes(): void
    {
        $argv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'wayfinder:generate'];

        try {
            config(['features' => ['registration' => false, 'password_confirmation' => false]]);

            (new FeatureFlagServiceProvider($this->app))->register();

            $this->assertTrue(config('features.registration'));
            $this->assertTrue(config('features.password_confirmation'));
        } finally {
            $_SERVER['argv'] = $argv;
        }
    }
}
