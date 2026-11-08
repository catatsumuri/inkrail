<?php

namespace Tests\Concerns;

trait PasswordResetThrottleHelpers
{
    protected function attemptReset(string $email)
    {
        return $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $email,
            'password' => 'new-secret-value',
            'password_confirmation' => 'new-secret-value',
        ]);
    }
}
