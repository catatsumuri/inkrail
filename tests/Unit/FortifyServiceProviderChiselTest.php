<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Chisel\Filesystem\File;
use Tests\TestCase;

class FortifyServiceProviderChiselTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Password reset has nothing to do with the registration feature, but
     * Chisel deletes everything between a tag's markers verbatim, imports
     * included. ResetUserPassword's import must stay outside the registration
     * markers (and must not be pulled back inside them by an import-sorting
     * tool later), or disabling registration leaves resetUserPasswordsUsing()
     * pointing at a class that no longer resolves.
     */
    public function test_disabling_registration_keeps_reset_user_password_resolvable(): void
    {
        $directory = sys_get_temp_dir().'/chisel-fortify-test-'.uniqid();
        mkdir($directory);

        try {
            copy(
                base_path('app/Providers/FortifyServiceProvider.php'),
                $directory.'/FortifyServiceProvider.php',
            );

            (new File($directory))->removeSection('FortifyServiceProvider.php', 'registration');

            $result = file_get_contents($directory.'/FortifyServiceProvider.php');

            $this->assertStringContainsString('Fortify::resetUserPasswordsUsing(ResetUserPassword::class)', $result);
            $this->assertMatchesRegularExpression(
                '/^use App\\\\Actions\\\\Fortify\\\\ResetUserPassword;$/m',
                $result,
            );
        } finally {
            @unlink($directory.'/FortifyServiceProvider.php');
            @rmdir($directory);
        }
    }
}
