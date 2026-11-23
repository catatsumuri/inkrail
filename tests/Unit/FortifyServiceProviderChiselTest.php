<?php

use App\Actions\Fortify\ResetUserPassword;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Tests\TestCase;

uses(TestCase::class);

test('password reset action remains resolvable', function () {
    expect(app(ResetsUserPasswords::class))->toBeInstanceOf(ResetUserPassword::class);
});
