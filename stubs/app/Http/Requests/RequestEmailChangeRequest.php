<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'), // Ensure new email is not already taken
                'different:email', // Ensure new email is different from current email
            ],
            'password' => [
                'required',
                'string',
                'current_password', // Verify current password for security
            ],
        ];
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_email.required' => 'Please provide a new email address.',
            'new_email.email' => 'Please provide a valid email address.',
            'new_email.unique' => 'This email address is already in use.',
            'new_email.different' => 'The new email must be different from your current email.',
            'password.required' => 'Please confirm your current password.',
            'password.current_password' => 'The provided password is incorrect.',
        ];
    }
}
