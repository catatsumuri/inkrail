<?php

namespace App\Http\Requests\Settings;

use App\Actions\LogSecurityEvent;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (config('fortify.lowercase_usernames') && is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }

        if (is_string($this->input('username'))) {
            $this->merge(['username' => Str::lower($this->input('username'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            // Accounts created before usernames existed may leave it empty,
            // but a username that has been set can be changed, never removed.
            'username' => $this->usernameRules($this->user()->id, required: $this->user()->username !== null),
            'current_password' => $this->isChangingEmail() || $this->isChangingUsername()
                ? ['bail', ...$this->currentPasswordRules()]
                : ['nullable'],
        ];
    }

    /**
     * Record a rejected email change before the validation error is returned.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('current_password')) {
            if ($this->isChangingEmail()) {
                app(LogSecurityEvent::class)->failure('email.change_failed', $this->user());
            }

            if ($this->isChangingUsername()) {
                app(LogSecurityEvent::class)->failure('username.change_failed', $this->user());
            }
        }

        parent::failedValidation($validator);
    }

    /**
     * Determine whether the request changes the user's email address.
     */
    protected function isChangingEmail(): bool
    {
        $email = $this->input('email');

        return ! is_string($email) || $email !== $this->user()->email;
    }

    /**
     * Determine whether the request changes the user's username.
     */
    protected function isChangingUsername(): bool
    {
        $username = $this->input('username');

        return (is_string($username) ? $username : null) !== $this->user()->username;
    }

    /**
     * Get the validated data without the password confirmation field.
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return Arr::only($this->validated(), ['name', 'username', 'email']);
    }
}
