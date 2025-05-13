<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            // For token-based requests
            if ($request->hasHeader('X-Request-Token')) {
                return response()->json(['message' => 'Email already verified']);
            }

            return redirect()->intended('/dashboard');
        }

        // Use API route for verification if this is an API request
        $useApiRoute = $request->hasHeader('X-Request-Token');
        $request->user()->sendEmailVerificationNotification($useApiRoute);

        return response()->json(['status' => 'verification-link-sent']);
    }
}
