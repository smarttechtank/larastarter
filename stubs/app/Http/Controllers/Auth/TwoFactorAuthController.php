<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\TwoFactorVerifyRequest;
use App\Http\Requests\Auth\TwoFactorSetupRequest;

class TwoFactorAuthController extends AppBaseController
{
    /**
     * Get Google 2FA setup information (QR code) for the authenticated user.
     */
    public function setup(): JsonResponse
    {
        $user = Auth::user();

        if ($user->two_factor_enabled) {
            return $this->sendError('Two-factor authentication is already enabled.', 422);
        }

        // Generate secret if not exists
        $secret = $user->getGoogle2FASecret();
        $qrCodeUrl = $user->getGoogle2FAQRCodeUrl();

        // Generate QR code as SVG
        $google2fa = app('pragmarx.google2fa');
        $qrCodeImage = $google2fa->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );

        $data = [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_svg' => $qrCodeImage,
        ];

        return $this->sendResponse($data, 'Google 2FA setup information generated.');
    }

    /**
     * Enable two-factor authentication for the authenticated user.
     */
    public function enable(TwoFactorSetupRequest $request): JsonResponse|Response
    {
        $user = Auth::user();

        // Verify the provided code first
        if (!$user->verifyGoogle2FACode($request->code)) {
            return $this->sendError('Invalid Google 2FA code.', 422);
        }

        // Enable 2FA
        $user->two_factor_enabled = true;
        $user->save();

        // Generate recovery codes when 2FA is enabled
        $recoveryCodes = $user->generateRecoveryCodes();

        $data = [
            'user' => $user,
            'recovery_codes' => $recoveryCodes
        ];

        $message = 'Two-factor authentication has been enabled successfully. Please save your recovery codes in a safe place.';

        // Check if token-based authentication is used
        if ($request->expectsJson() || $request->hasHeader('X-Request-Token')) {
            return $this->sendResponse($data, $message);
        }

        return response()->noContent();
    }

    /**
     * Disable two-factor authentication for the authenticated user.
     */
    public function disable(): JsonResponse|Response
    {
        $user = Auth::user();

        // Disable 2FA and reset secret and recovery codes
        $user->two_factor_enabled = false;
        $user->resetGoogle2FASecret();
        $user->resetRecoveryCodes();
        $user->save();

        $message = 'Two-factor authentication has been disabled.';

        return $this->sendResponse($user, $message);
    }

    /**
     * Verify the Google 2FA code.
     */
    public function verify(TwoFactorVerifyRequest $request): JsonResponse|Response
    {
        // Find the user by email
        $email = $request->email;
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            return $this->sendError('User not found.', 404);
        }

        if (!$user->two_factor_enabled) {
            return $this->sendError('Two-factor authentication is not enabled for this user.', 422);
        }

        // Verify the Google 2FA code or recovery code
        $codeVerified = false;
        $usedRecoveryCode = false;

        // First try Google 2FA code
        if ($user->verifyGoogle2FACode($request->code)) {
            $codeVerified = true;
        }
        // If Google 2FA fails, try recovery code
        elseif ($user->verifyRecoveryCode($request->code)) {
            $codeVerified = true;
            $usedRecoveryCode = true;
        }

        if (!$codeVerified) {
            return $this->sendError('Invalid Google 2FA code or recovery code.', 422);
        }

        // Login the user
        Auth::login($user);

        // Check if token-based authentication is requested
        if ($request->expectsJson() || $request->hasHeader('X-Request-Token')) {
            $token = $user->createToken('auth-token')->plainTextToken;
            $user->load('role');

            $data = [
                'user' => $user,
                'token' => $token
            ];

            $message = $usedRecoveryCode
                ? 'Recovery code verification successful. Consider regenerating your recovery codes.'
                : 'Google 2FA verification successful.';

            return $this->sendResponse($data, $message);
        }

        // For session-based authentication
        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Get recovery codes status for the authenticated user.
     */
    public function recoveryCodes(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->two_factor_enabled) {
            return $this->sendError('Two-factor authentication is not enabled.', 422);
        }

        $unusedCodes = $user->getUnusedRecoveryCodes();
        $totalCodes = count($user->getRecoveryCodes());
        $usedCount = $totalCodes - count($unusedCodes);

        $data = [
            'total_codes' => $totalCodes,
            'unused_codes' => count($unusedCodes),
            'used_codes' => $usedCount,
            'has_unused_codes' => $user->hasUnusedRecoveryCodes()
        ];

        return $this->sendResponse($data, 'Recovery codes status retrieved.');
    }

    /**
     * Regenerate recovery codes for the authenticated user.
     */
    public function regenerateRecoveryCodes(): JsonResponse|Response
    {
        $user = Auth::user();

        if (!$user->two_factor_enabled) {
            return $this->sendError('Two-factor authentication is not enabled.', 422);
        }

        // Generate new recovery codes
        $recoveryCodes = $user->generateRecoveryCodes();

        $data = [
            'recovery_codes' => $recoveryCodes,
            'message' => 'Your old recovery codes have been invalidated. Please save these new codes in a safe place.'
        ];

        // Check if token-based authentication is used
        if (request()->expectsJson() || request()->hasHeader('X-Request-Token')) {
            return $this->sendResponse($data, 'Recovery codes regenerated successfully.');
        }

        return response()->noContent();
    }
}
