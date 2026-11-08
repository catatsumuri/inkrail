<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SessionManagementHelpers
{
    /**
     * @return array{0: User, 1: array{cookie: string, id: string}, 2: array{cookie: string, id: string, remember: array{name: string, value: string}}}
     */
    protected function userWithRememberedSecondSession(): array
    {
        $user = User::factory()->create();
        $current = $this->signIn($user);
        $attacker = $this->signInRemembered($user);
        $this->confirmPassword($current['cookie']);

        return [$user, $current, $attacker];
    }

    /**
     * Keeps the same database but under a different connection name, so
     * sessions and users can no longer share one transaction.
     */
    protected function useSeparateSessionConnection(): void
    {
        $default = config('database.default');

        config([
            'database.connections.session_alt' => config("database.connections.{$default}"),
            'session.connection' => 'session_alt',
        ]);

        DB::purge('session_alt');
        $pdo = DB::connection()->getPdo();
        DB::connection('session_alt')->setPdo($pdo)->setReadPdo($pdo);
        $this->app['session']->forgetDrivers();
    }

    /**
     * Stores sessions in their own in-memory SQLite database while users stay on
     * the default connection, so the two are independent connections that cannot
     * share a transaction (whatever driver the default connection uses).
     */
    protected function useSqliteSessionConnection(): void
    {
        config([
            'database.connections.session_sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'session.connection' => 'session_sqlite',
        ]);

        DB::purge('session_sqlite');
        Schema::connection('session_sqlite')->create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        $this->app['session']->forgetDrivers();
    }

    /**
     * @return array{cookie: string, id: string}
     */
    protected function signIn(User $user): array
    {
        $this->unencryptedCookies = [];
        $this->startNewClient();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();

        return [
            'cookie' => $response->getCookie(config('session.cookie'), false)->getValue(),
            'id' => $response->getCookie(config('session.cookie'))->getValue(),
        ];
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
     * @return array{cookie: string, id: string, remember: array{name: string, value: string}}
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
        $remember = $response->getCookie($name, false);
        $this->assertNotNull($remember, 'No remember me cookie was issued.');

        return [
            'cookie' => $response->getCookie(config('session.cookie'), false)->getValue(),
            'id' => $response->getCookie(config('session.cookie'))->getValue(),
            'remember' => ['name' => $name, 'value' => $remember->getValue()],
        ];
    }

    protected function confirmPassword(string $cookie): void
    {
        $this->asClient($cookie)
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect();
    }

    protected function revocationUrl(string $route, string $parameter): string
    {
        return $parameter === '' ? route($route) : route($route, $parameter);
    }

    protected function storeSession(
        string $id,
        User $user,
        string $ipAddress = '2001:db8:abcd:12:1234:5678:90ab:cdef',
        string $userAgent = 'Mozilla/5.0 (Linux) Chrome/130.0 Safari/537.36',
        ?int $lastActivity = null,
    ): void {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => $lastActivity ?? now()->timestamp,
        ]);
    }
}
