<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'test.user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_username_and_email_are_stored_in_lowercase()
    {
        $this->post(route('register.store'), $this->payload([
            'username' => 'Mixed.Case',
            'email' => 'Mixed@Example.COM',
        ]))->assertSessionHasNoErrors();

        $user = User::sole();

        $this->assertSame('mixed.case', $user->username);
        $this->assertSame('mixed@example.com', $user->email);
    }

    public function test_a_username_is_required()
    {
        $this->post(route('register.store'), $this->payload(['username' => null]))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    #[DataProvider('invalidUsernames')]
    public function test_invalid_usernames_are_rejected(string $username)
    {
        $this->post(route('register.store'), $this->payload(['username' => $username]))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUsernames(): array
    {
        return [
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 31)],
            'contains an at sign' => ['someone@example.com'],
            'contains a space' => ['with space'],
            'starts with a separator' => ['.dotfirst'],
            'ends with a separator' => ['dotlast.'],
            'unsupported characters' => ['üser'],
        ];
    }

    public function test_usernames_must_be_unique_regardless_of_case()
    {
        User::factory()->withUsername('taken')->create();

        $this->post(route('register.store'), $this->payload(['username' => 'TAKEN']))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_registered_user_can_log_in_with_the_username()
    {
        $this->post(route('register.store'), $this->payload(['username' => 'newcomer']));
        $user = User::sole();

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), ['login' => 'newcomer', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'username' => 'test.user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }
}
