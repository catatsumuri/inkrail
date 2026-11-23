<?php

namespace Tests\Unit;

use App\Auth\UsernameOrEmailUserProvider;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsernameOrEmailUserProviderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): UsernameOrEmailUserProvider
    {
        return new UsernameOrEmailUserProvider($this->app->make(Hasher::class), User::class);
    }

    public function test_it_finds_a_user_by_username_or_email(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->assertTrue($this->provider()->retrieveByCredentials(['login' => 'alice'])->is($user));
        $this->assertTrue($this->provider()->retrieveByCredentials(['login' => $user->email])->is($user));
        $this->assertTrue($this->provider()->retrieveByCredentials(['login' => 'ALICE'])->is($user));
    }

    public function test_extra_credentials_must_still_match(): void
    {
        $user = User::factory()->withUsername('alice')->create(['name' => 'Alice']);

        $this->assertTrue($this->provider()->retrieveByCredentials(['login' => 'alice', 'name' => 'Alice'])->is($user));
        $this->assertNull($this->provider()->retrieveByCredentials(['login' => 'alice', 'name' => 'Somebody Else']));
        $this->assertNull($this->provider()->retrieveByCredentials(['login' => $user->email, 'name' => 'Somebody Else']));
    }

    public function test_extra_array_and_closure_conditions_are_applied(): void
    {
        $user = User::factory()->withUsername('alice')->create(['name' => 'Alice']);

        $this->assertTrue($this->provider()->retrieveByCredentials(['login' => 'alice', 'name' => ['Alice', 'Bob']])->is($user));
        $this->assertNull($this->provider()->retrieveByCredentials(['login' => 'alice', 'name' => ['Bob']]));

        $this->assertNull($this->provider()->retrieveByCredentials([
            'login' => 'alice',
            fn (Builder $query) => $query->where('name', 'Nobody'),
        ]));
    }

    public function test_password_is_never_used_as_a_lookup_condition(): void
    {
        $user = User::factory()->withUsername('alice')->create();

        $this->assertTrue($this->provider()->retrieveByCredentials(['login' => 'alice', 'password' => 'anything'])->is($user));
    }

    public function test_an_empty_or_non_string_login_matches_nobody(): void
    {
        User::factory()->withUsername('alice')->create();

        $this->assertNull($this->provider()->retrieveByCredentials(['login' => '']));
        $this->assertNull($this->provider()->retrieveByCredentials(['login' => ['alice']]));
    }

    public function test_credentials_without_login_use_the_standard_lookup(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->provider()->retrieveByCredentials(['email' => $user->email])->is($user));
    }
}
