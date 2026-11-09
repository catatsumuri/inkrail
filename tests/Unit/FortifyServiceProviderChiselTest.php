<?php

use Laravel\Chisel\Filesystem\File;

/**
 * Password reset has nothing to do with the registration feature, but
 * Chisel deletes everything between a tag's markers verbatim, imports
 * included. ResetUserPassword's import must stay outside the registration
 * markers (and must not be pulled back inside them by an import-sorting
 * tool later), or disabling registration leaves resetUserPasswordsUsing()
 * pointing at a class that no longer resolves.
 */
test('disabling registration keeps reset user password resolvable', function () {
    $directory = sys_get_temp_dir().'/chisel-fortify-test-'.uniqid();
    mkdir($directory);

    try {
        copy(
            dirname(__DIR__, 2).'/app/Providers/FortifyServiceProvider.php',
            $directory.'/FortifyServiceProvider.php',
        );

        (new File($directory))->removeSection('FortifyServiceProvider.php', 'registration');

        $result = file_get_contents($directory.'/FortifyServiceProvider.php');

        $this->assertStringContainsString('Fortify::resetUserPasswordsUsing(ResetUserPassword::class)', $result);
        expect($result)->toMatch('/^use App\\\\Actions\\\\Fortify\\\\ResetUserPassword;$/m');
    } finally {
        @unlink($directory.'/FortifyServiceProvider.php');
        @rmdir($directory);
    }
});
