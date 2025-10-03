<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class VerifyEmailChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Verify that the token and email match the user's pending change request
        $user = User::find($this->route('id'));

        if (!$user) {
            return false;
        }

        // Check if there's a pending email change
        if (!$user->pending_email || !$user->email_change_token) {
            return false;
        }

        // Verify token matches
        if (!Hash::check($this->route('token'), $user->email_change_token)) {
            return false;
        }

        // Verify email matches the pending email
        if ($this->route('email') !== $user->pending_email) {
            return false;
        }

        // Check if the token hasn't expired (default 60 minutes)
        $expireTime = config('auth.verification.expire', 60);
        if ($user->email_change_requested_at->addMinutes($expireTime)->isPast()) {
            return false;
        }

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
            'id' => 'required|integer|exists:users,id',
            'token' => 'required|string',
            'email' => 'required|email',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
            'token' => $this->route('token'),
            'email' => $this->route('email'),
        ]);
    }
}
