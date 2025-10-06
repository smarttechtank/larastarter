<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

class RegisteredUserController extends AppBaseController
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response|JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
            // default role is user role
            'role_id' => Role::where('name', 'user')->first()->id,
        ]);

        event(new Registered($user));

        // Check if token-based authentication is requested
        if ($this->isTokenRequest($request)) {
            Auth::login($user);

            // For a new registration, always create a new token
            $token = $user->createToken('auth-token')->plainTextToken;

            $data = [
                'user' => $user,
                'token' => $token
            ];
            return $this->sendResponse($data, 'Registration successful.');
        }

        Auth::login($user);

        return response()->noContent();
    }

    /**
     * Check if the request is for token-based authentication.
     */
    private function isTokenRequest(Request $request): bool
    {
        return $request->hasHeader('X-Request-Token');
    }
}
