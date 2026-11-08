<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;
use Laravel\Fortify\Features;

trait SecurityEventAccessHelpers
{
    protected function verifiedViewer(string $listedAs = 'viewer@example.com'): User
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());
        config(['audit.security_viewers' => [$listedAs]]);

        return User::factory()->create(['email' => 'viewer@example.com']);
    }

    /**
     * A user as it exists when Chisel removes email verification: the model
     * no longer implements MustVerifyEmail, so the verified middleware passes.
     */
    protected function userWithoutEmailVerification(string $email): AuthUser
    {
        $user = new class extends AuthUser {};
        $user->forceFill(['id' => 1, 'email' => $email]);

        return $user;
    }

    protected function recordEvent(string $logName, string $event, string $result): void
    {
        activity($logName)->event($event)->withProperties(['result' => $result])->log($event);
    }
}
