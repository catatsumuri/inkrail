<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Passkeys\Passkey;

trait PasskeySecurityEventLoggingHelpers
{
    use BootsWithFeaturesDisabled;

    protected array $enabledFeatures = ['passkeys', 'password_confirmation'];

    protected array $disabledFeatures = [];

    protected function actingAsPasswordConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    protected function createPasskey(User $user, string $name = 'Laptop'): Passkey
    {
        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => fake()->unique()->uuid(),
            'credential' => ['id' => 'x'],
        ]);
    }
}
