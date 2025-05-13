<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\TwoFactorVerifyRequest;
use App\Http\Requests\Auth\TwoFactorToggleRequest;

class TwoFactorAuthController extends Controller
{
  /**
   * Toggle two-factor authentication for the authenticated user.
   */
  public function toggle(TwoFactorToggleRequest $request): JsonResponse|Response
  {
    $user = Auth::user();

    // Update the two_factor_enabled status
    $user->two_factor_enabled = $request->enabled;
    $user->save();

    // If enabling 2FA, reset any existing code
    if ($user->two_factor_enabled) {
      $user->resetTwoFactorCode();
    }

    $message = $user->two_factor_enabled
      ? 'Two-factor authentication has been enabled.'
      : 'Two-factor authentication has been disabled.';

    // Check if token-based authentication is used
    if ($request->expectsJson() || $request->hasHeader('X-Request-Token')) {
      return response()->json([
        'message' => $message,
        'user' => $user
      ]);
    }

    return response()->noContent();
  }

  /**
   * Verify the two-factor authentication code.
   */
  public function verify(TwoFactorVerifyRequest $request): JsonResponse|Response
  {
    // Find the user by email
    $email = $request->email;
    $user = \App\Models\User::where('email', $email)->first();

    if (!$user) {
      return response()->json([
        'message' => 'User not found.'
      ], 404);
    }

    // Verify the code
    if (!$user->verifyTwoFactorCode($request->code)) {
      return response()->json([
        'message' => 'Invalid or expired two-factor code.'
      ], 422);
    }

    // Login the user
    Auth::login($user);

    // Check if token-based authentication is requested
    if ($request->expectsJson() || $request->hasHeader('X-Request-Token')) {
      $token = $user->createToken('auth-token')->plainTextToken;
      $user->load('role');

      return response()->json([
        'message' => 'Two-factor authentication successful.',
        'user' => $user,
        'token' => $token
      ]);
    }

    // For session-based authentication
    $request->session()->regenerate();

    return response()->noContent();
  }
}
