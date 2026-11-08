<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Passkeys\Passkey;

trait PasskeySecurityEventLoggingHelpers
{
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
