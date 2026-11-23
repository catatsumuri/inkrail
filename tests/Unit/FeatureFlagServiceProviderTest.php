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
}
