<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input = $this->normalize($input);

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'] ?? null,
            'password' => $input['password'],
        ]);
    }

    /**
     * Lowercase the identifiers that are stored in lowercase.
     *
     * Fortify only lowercases the field named by its "username" setting,
     * which is now the login field, so the registration fields are handled here.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        foreach (['username', 'email'] as $field) {
            if (isset($input[$field]) && is_string($input[$field]) && ($field === 'username' || config('fortify.lowercase_usernames'))) {
                $input[$field] = Str::lower($input[$field]);
            }
        }

        return $input;
    }
}
