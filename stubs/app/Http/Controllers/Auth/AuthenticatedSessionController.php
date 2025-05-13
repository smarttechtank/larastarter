<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
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
            try {
                // Generate a new 2FA code
                $code = $user->generateTwoFactorCode();

                // Send the 2FA code to the user
                $user->sendTwoFactorCodeNotification();

                // Logout the user as they need to verify the 2FA code
                Auth::logout();

                // Return response based on request type
                if ($request->isTokenRequest()) {
                    return response()->json([
                        'message' => 'Two-factor authentication code has been sent to your email.',
                        'two_factor_auth_required' => true,
                        'email' => $user->email
                    ], 200);
                }

                return response()->json([
                    'two_factor_auth_required' => true,
                    'email' => $user->email
                ], 200);
            } catch (\Exception $e) {
                // Re-authenticate the user since we're skipping 2FA due to an error
                Auth::login($user);

                if ($request->isTokenRequest()) {
                    $user->load('role');
                    $token = $user->createToken('auth-token')->plainTextToken;

                    return response()->json([
                        'user' => $user,
                        'token' => $token,
                        'warning' => 'Two-factor authentication is enabled but the code could not be sent. You have been logged in without 2FA verification.'
                    ]);
                }

                $request->session()->regenerate();

                return response()->noContent();
            }
        }

        // If 2FA is not enabled, continue with normal authentication
        if ($request->isTokenRequest()) {
            $user->load('role');

            // Create a new token for each login
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token
            ]);
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
            return response()->json([
                'message' => 'Token revoked successfully',
                'status' => 'success'
            ]);
        }

        // For session-based authentication
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
