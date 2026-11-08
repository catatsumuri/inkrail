<?php

namespace Tests\Concerns;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

trait MfaSecurityEventLoggingHelpers
{
    protected function actingAsPasswordConfirmed(?User $user = null): static
    {
        return $this->actingAs($user ?? User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()]);
    }

    protected function enableAndConfirmTwoFactor(): User
    {
        $user = User::factory()->create();

        $this->actingAsPasswordConfirmed($user)->post('/user/two-factor-authentication');

        $secret = decrypt($user->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAsPasswordConfirmed($user)->post('/user/confirmed-two-factor-authentication', ['code' => $code]);

        return $user->fresh();
    }
}
