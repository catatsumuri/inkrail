<?php

namespace App\Http\Requests\Settings;

use App\Actions\LogSecurityEvent;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'current_password' => $this->isChangingEmail()
                ? ['bail', ...$this->currentPasswordRules()]
                : ['nullable'],
        ];
    }

    /**
     * Record a rejected email change before the validation error is returned.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->isChangingEmail() && $validator->errors()->has('current_password')) {
            app(LogSecurityEvent::class)->failure('email.change_failed', $this->user());
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
     * Get the validated data without the password confirmation field.
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return Arr::only($this->validated(), ['name', 'email']);
    }
}
