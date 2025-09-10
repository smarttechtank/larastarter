<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends AppBaseController
{
    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            if ($this->isTokenRequest($request)) {
                return $this->sendError(__($status));
            } else {
                throw ValidationException::withMessages([
                    'email' => [__($status)],
                ]);
            }
        }

        return $this->sendSuccess(__($status));
    }

    /**
     * Check if the request is for token-based authentication.
     */
    private function isTokenRequest(Request $request): bool
    {
        return $request->hasHeader('X-Request-Token');
    }
}
