<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        $rules = User::$rules;
        $rules['role_id'] = 'nullable|exists:roles,id';

        // Validate email uniqueness for updates (except for the current user)
        $userId = $this->route('user') ?? auth()->id();
        $rules['email'] = 'required|string|max:255|email|unique:users,email,' . $userId;

        unset($rules['password']);
        return $rules;
    }
}
