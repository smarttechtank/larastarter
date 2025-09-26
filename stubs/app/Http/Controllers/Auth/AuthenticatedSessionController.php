<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends AppBaseController
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response|JsonResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Check if 2FA is enabled for this user
        if ($user->two_factor_enabled) {
            // Logout the user as they need to verify with Google 2FA
            Auth::logout();

            // Return response indicating 2FA verification is required
            if ($request->isTokenRequest()) {
                $data = [
                    'two_factor_auth_required' => true,
                    'email' => $user->email,
                    'message' => 'Please enter the 6-digit code from your Google Authenticator app'
                ];
                return $this->sendResponse($data, 'Google 2FA verification required.');
            }

            $data = [
                'two_factor_auth_required' => true,
                'email' => $user->email,
                'message' => 'Please enter the 6-digit code from your Google Authenticator app'
            ];
            return $this->sendResponse($data, 'Google 2FA verification required.');
        }

        // If 2FA is not enabled, continue with normal authentication
        if ($request->isTokenRequest()) {
            $user->load('role');

            // Create a new token for each login
            $token = $user->createToken('auth-token')->plainTextToken;

            $data = [
                'user' => $user,
                'token' => $token
            ];
            return $this->sendResponse($data, 'Login successful.');
        }

        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response|JsonResponse
    {
        // For token-based authentication, revoke the token
        if ($request->hasHeader('X-Request-Token') && $request->user()) {
            $request->user()->currentAccessToken()->delete();
            return $this->sendSuccess('Token revoked successfully');
        }

        // For session-based authentication
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
