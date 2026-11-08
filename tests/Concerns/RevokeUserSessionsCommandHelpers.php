<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait RevokeUserSessionsCommandHelpers
{
    protected function emptyConfirmationQuestion(User $user): string
    {
        return "Revoke 0 stored sessions and reset remember me logins for {$user->name} ({$user->email}, ID {$user->getKey()})?";
    }

    protected function confirmationQuestion(User $user, int $count): string
    {
        return "Revoke {$count} session(s) for {$user->name} ({$user->email}, ID {$user->getKey()})?";
    }

    protected function signIn(User $user): string
    {
        $this->unencryptedCookies = [];
        $this->startNewClient();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();

        return $response->getCookie(config('session.cookie'), false)->getValue();
    }

    /**
     * @return array{name: string, value: string}
     */
    protected function signInRemembered(User $user): array
    {
        $this->unencryptedCookies = [];
        $this->startNewClient();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect();

        $name = Auth::guard('web')->getRecallerName();
        $cookie = $response->getCookie($name, false);
        $this->assertNotNull($cookie, 'No remember me cookie was issued.');

        return ['name' => $name, 'value' => $cookie->getValue()];
    }

    /**
     * @param  array{name: string, value: string}|null  $remember
     */
    protected function asClient(?string $cookie, ?array $remember = null): static
    {
        $this->startNewClient();
        $this->unencryptedCookies = [];

        if ($cookie !== null) {
            $this->withUnencryptedCookie(config('session.cookie'), $cookie);
        }

        if ($remember !== null) {
            $this->withUnencryptedCookie($remember['name'], $remember['value']);
        }

        return $this;
    }

    /**
     * Drop every per-client instance so the next request is served as a new
     * browser, the way a separate PHP process would serve it.
     */
    protected function startNewClient(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('auth.driver');
    }

    protected function storeSession(string $id, ?User $user, ?int $lastActivity = null): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user?->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0 (Linux) Chrome/130.0 Safari/537.36',
            'payload' => '',
            'last_activity' => $lastActivity ?? now()->timestamp,
        ]);
    }
}
