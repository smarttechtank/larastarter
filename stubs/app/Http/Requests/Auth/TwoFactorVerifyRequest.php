<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
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
                }
            ],
            'email' => 'required|string|email',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'A verification code is required.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
