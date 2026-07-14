<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorDisableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Disabling two-factor authentication is a security-sensitive action, so
     * the user must reauthenticate with either their current password or a
     * valid Google 2FA / recovery code before it is allowed.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required_without:code', 'string', 'current_password'],
            'code' => [
                'required_without:password',
                'string',
                function ($attribute, $value, $fail) {
                    // Allow 6-digit numeric codes (Google 2FA)
                    if (preg_match('/^[0-9]{6}$/', $value)) {
                        return;
                    }

                    // Allow recovery codes (alphanumeric with dashes, 10-30 characters)
                    if (preg_match('/^[A-Za-z0-9\-]{10,30}$/', $value)) {
                        return;
                    }

                    $fail('Invalid Google 2FA code or recovery code.');
                },
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'password.required_without' => 'Please confirm your password or provide a verification code to disable two-factor authentication.',
            'password.current_password' => 'The provided password is incorrect.',
            'code.required_without' => 'Please confirm your password or provide a verification code to disable two-factor authentication.',
        ];
    }
}
