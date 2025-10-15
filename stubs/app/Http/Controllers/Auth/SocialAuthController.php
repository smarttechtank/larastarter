<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\MobileOAuthRequest;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;

class SocialAuthController extends AppBaseController
{
    /**
     * Get frontend login URL with base URL.
     */
    private function getFrontendLoginUrl(): string
    {
        return config('app.frontend_url') . '/login';
    }

    /**
     * Get frontend dashboard URL with base URL.
     */
    private function getFrontendDashboardUrl(): string
    {
        return config('app.frontend_url') . '/dashboard';
    }

    /**
     * Get frontend settings URL with base URL.
     */
    private function getFrontendSettingsUrl(): string
    {
        return config('app.frontend_url') . '/settings?tab=accounts';
    }

    /**
     * Redirect to OAuth provider for authentication.
     */
    public function redirectToProvider(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $response = $this->ensureSocialAuthEnabled($request);
        if ($response) {
            return $response;
        }

        $this->validateProvider($provider);

        try {
            $redirectUrl = Socialite::driver($provider)->redirect()->getTargetUrl();

            if ($this->isTokenRequest($request)) {
                return $this->sendResponse(['redirect_url' => $redirectUrl], 'Redirect URL generated successfully');
            }

            return Socialite::driver($provider)->redirect();
        } catch (\Exception $e) {
            if ($this->isTokenRequest($request)) {
                return $this->sendError('OAuth redirect failed: ' . $e->getMessage(), 500);
            }

            return redirect($this->getFrontendLoginUrl() . '?error=' . urlencode('OAuth authentication failed. Please try again.'));
        }
    }

    /**
     * Handle OAuth provider callback.
     */
    public function handleProviderCallback(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $response = $this->ensureSocialAuthEnabled($request);
        if ($response) {
            return $response;
        }

        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            // Handle both InvalidStateException and other OAuth exceptions
            if ($this->isTokenRequest($request)) {
                return $this->sendError('OAuth authentication failed: ' . $e->getMessage(), 400);
            }

            return redirect($this->getFrontendLoginUrl() . '?error=' . urlencode('OAuth authentication failed. Please try again.'));
        }

        // Check if user is already authenticated and wants to link account
        if (Auth::check()) {
            return $this->linkProviderToExistingUser($request, $provider, $socialUser);
        }

        // Try to authenticate or register user
        return $this->authenticateOrRegisterUser($request, $provider, $socialUser);
    }

    /**
     * Link OAuth provider to currently authenticated user.
     */
    protected function linkProviderToExistingUser(Request $request, string $provider, $socialUser): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $providerId = (string) $socialUser->id;

        // Check if this provider ID is already linked to another user
        $existingUser = $this->findUserByProvider($provider, $providerId);
        if ($existingUser && $existingUser->id !== $user->id) {
            $message = "This {$provider} account is already linked to another user.";

            if ($this->isTokenRequest($request)) {
                return $this->sendError($message, 409);
            }

            return redirect($this->getFrontendSettingsUrl() . '&error=' . urlencode($message));
        }

        // Download and store avatar if user doesn't have one
        $this->downloadAvatarIfNeeded($user, $socialUser);

        // Link the provider to current user
        $this->linkProvider($user, $provider, $socialUser);

        $message = ucfirst($provider) . ' account linked successfully.';

        if ($this->isTokenRequest($request)) {
            $user->load('role');
            return $this->sendResponse(['user' => $user], $message);
        }

        return redirect($this->getFrontendSettingsUrl() . '&success=' . urlencode($message));
    }

    /**
     * Authenticate existing user or register new user via OAuth.
     */
    protected function authenticateOrRegisterUser(Request $request, string $provider, $socialUser): RedirectResponse|JsonResponse
    {
        $providerId = (string) $socialUser->id;
        $email = $socialUser->email;

        // Try to find user by provider ID first
        $user = $this->findUserByProvider($provider, $providerId);

        if ($user) {
            // User exists with this provider, log them in
            return $this->authenticateUser($request, $user, $provider, $socialUser);
        }

        // Try to find user by email
        $user = User::where('email', $email)->first();

        if ($user) {
            // Download and store avatar if user doesn't have one
            $this->downloadAvatarIfNeeded($user, $socialUser);

            // User exists with same email, link the provider and log them in
            $this->linkProvider($user, $provider, $socialUser);
            return $this->authenticateUser($request, $user, $provider, $socialUser);
        }

        // User doesn't exist, check if registration is enabled
        if (!Config::get('auth.registration_enabled', true)) {
            $message = 'Registration is disabled. You can only link ' . ucfirst($provider) . ' to an existing account.';

            if ($this->isTokenRequest($request)) {
                return $this->sendError($message, 403);
            }

            return redirect($this->getFrontendLoginUrl() . '?error=' . urlencode($message));
        }

        // Create new user
        $user = $this->createUserFromSocialData($provider, $socialUser);
        return $this->authenticateUser($request, $user, $provider, $socialUser);
    }

    /**
     * Authenticate user and handle 2FA if enabled.
     */
    protected function authenticateUser(Request $request, User $user, string $provider, $socialUser): RedirectResponse|JsonResponse
    {
        // Update provider tokens
        $this->linkProvider($user, $provider, $socialUser);

        // Mark email as verified if not already verified (OAuth providers verify emails)
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Check if 2FA is enabled
        if ($user->two_factor_enabled) {
            // Store user ID in session for 2FA verification
            session(['2fa_user_id' => $user->id, '2fa_remember' => false]);

            if ($this->isTokenRequest($request)) {
                return $this->sendResponse([
                    'two_factor_auth_required' => true,
                    'email' => $user->email,
                    'message' => 'Please enter the 6-digit code from your Google Authenticator app'
                ], 'Google 2FA verification required.');
            }

            return redirect($this->getFrontendLoginUrl() . '?requires_2fa=true&email=' . urlencode($user->email));
        }

        // Log the user in
        Auth::login($user, true);

        if ($this->isTokenRequest($request)) {
            $user->load('role');
            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendResponse([
                'user' => $user,
                'token' => $token
            ], 'Login successful via ' . ucfirst($provider));
        }

        return redirect($this->getFrontendDashboardUrl() . '?success=' . urlencode('Successfully logged in via ' . ucfirst($provider)));
    }

    /**
     * Find user by OAuth provider.
     */
    protected function findUserByProvider(string $provider, string $providerId): ?User
    {
        $column = $provider . '_id';
        return User::where($column, $providerId)->first();
    }

    /**
     * Link OAuth provider to user.
     */
    protected function linkProvider(User $user, string $provider, $socialUser): void
    {
        $token = $socialUser->token;
        $refreshToken = $socialUser->refreshToken ?? null;

        if ($provider === 'google') {
            $user->linkGoogleAccount((string) $socialUser->id, $token, $refreshToken);
        } elseif ($provider === 'github') {
            $user->linkGithubAccount((string) $socialUser->id, $token, $refreshToken);
        }
    }

    /**
     * Create new user from social provider data.
     */
    protected function createUserFromSocialData(string $provider, $socialUser): User
    {
        // Get default role
        $defaultRole = \App\Models\Role::where('name', 'user')->first();

        $userData = [
            'name' => $socialUser->name ?? $socialUser->nickname ?? 'User',
            'email' => $socialUser->email,
            'email_verified_at' => now(),
            'password' => null, // OAuth users don't have a password initially
            'role_id' => $defaultRole?->id ?? 1,
        ];

        // Add provider-specific data
        if ($provider === 'google') {
            $userData['google_id'] = (string) $socialUser->id;
            $userData['google_token'] = $socialUser->token;
            $userData['google_refresh_token'] = $socialUser->refreshToken;
        } elseif ($provider === 'github') {
            $userData['github_id'] = (string) $socialUser->id;
            $userData['github_token'] = $socialUser->token;
            $userData['github_refresh_token'] = $socialUser->refreshToken;
        }

        // Download and store avatar if available
        if ($socialUser->avatar) {
            $avatarPath = $this->downloadAvatarFromUrl($socialUser->avatar, $provider);
            if ($avatarPath) {
                $userData['avatar'] = $avatarPath;
            }
        }

        return User::create($userData);
    }

    /**
     * Unlink OAuth provider from authenticated user.
     */
    public function unlinkProvider(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $response = $this->ensureSocialAuthEnabled($request);
        if ($response) {
            return $response;
        }

        $this->validateProvider($provider);

        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            if ($this->isTokenRequest($request)) {
                return $this->sendError('Unauthenticated', 401);
            }
            return redirect($this->getFrontendLoginUrl());
        }

        // Check if user has password or another provider linked
        $hasKnownPassword = $this->userHasKnownPassword($user);
        $hasOtherProvider = ($provider === 'google' && $user->hasGithubLinked()) ||
            ($provider === 'github' && $user->hasGoogleLinked());

        if (!$hasKnownPassword && !$hasOtherProvider) {
            $message = 'Cannot unlink ' . ucfirst($provider) . ' as it\'s your only authentication method. Please set a password first.';

            if ($this->isTokenRequest($request)) {
                return $this->sendError($message, 400);
            }

            return redirect($this->getFrontendSettingsUrl() . '&error=' . urlencode($message));
        }

        // Unlink the provider
        if ($provider === 'google') {
            $user->unlinkGoogleAccount();
        } elseif ($provider === 'github') {
            $user->unlinkGithubAccount();
        }

        $message = ucfirst($provider) . ' account unlinked successfully.';

        if ($this->isTokenRequest($request)) {
            $user->load('role');
            return $this->sendResponse(['user' => $user], $message);
        }

        return redirect($this->getFrontendSettingsUrl() . '&success=' . urlencode($message));
    }

    /**
     * Authenticate user with mobile OAuth token (for native mobile apps).
     *
     * @param MobileOAuthRequest $request
     * @param string $provider
     * @return JsonResponse
     */
    public function authenticateWithToken(MobileOAuthRequest $request, string $provider): JsonResponse
    {
        $response = $this->ensureSocialAuthEnabled($request);
        if ($response) {
            return $response;
        }

        $this->validateProvider($provider);

        try {
            // Verify token with provider and get user data
            $socialUser = $this->verifyTokenWithProvider($provider, $request->access_token, $request->id_token);

            if (!$socialUser) {
                return $this->sendError('Failed to verify token with provider', 401);
            }

            // Create mock request for authenticateOrRegisterUser
            $mockRequest = new Request();
            $mockRequest->headers->set('X-Request-Token', 'true');

            // Use existing authentication logic
            return $this->authenticateOrRegisterUser($mockRequest, $provider, $socialUser);
        } catch (\Exception $e) {
            Log::error('Mobile OAuth token verification failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Token verification failed: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Verify token with appropriate provider.
     *
     * @param string $provider
     * @param string $accessToken
     * @param string|null $idToken
     * @return object|null
     */
    protected function verifyTokenWithProvider(string $provider, string $accessToken, ?string $idToken = null): ?object
    {
        return match ($provider) {
            'google' => $this->verifyGoogleToken($accessToken, $idToken),
            'github' => $this->verifyGithubToken($accessToken),
            default => null,
        };
    }

    /**
     * Verify Google OAuth token and return user data.
     *
     * @param string $accessToken
     * @param string|null $idToken
     * @return object|null
     */
    protected function verifyGoogleToken(string $accessToken, ?string $idToken = null): ?object
    {
        try {
            // Prefer ID token verification as it's more secure and contains user info
            if ($idToken) {
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $idToken,
                ]);
            } else {
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'access_token' => $accessToken,
                ]);
            }

            if (!$response->successful()) {
                Log::warning('Google token verification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // Verify the token is for our application
            $clientId = config('services.google.client_id');
            if (isset($data['aud']) && $data['aud'] !== $clientId) {
                Log::warning('Google token audience mismatch', [
                    'expected' => $clientId,
                    'actual' => $data['aud'],
                ]);
                return null;
            }

            // Return user data in Socialite-like format
            return (object) [
                'id' => $data['sub'] ?? $data['user_id'] ?? null,
                'email' => $data['email'] ?? null,
                'name' => $data['name'] ?? null,
                'avatar' => $data['picture'] ?? null,
                'token' => $accessToken,
                'refreshToken' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Google token verification exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify GitHub OAuth token and return user data.
     *
     * @param string $accessToken
     * @return object|null
     */
    protected function verifyGithubToken(string $accessToken): ?object
    {
        try {
            // Get user info from GitHub API
            $response = Http::withToken($accessToken)
                ->get('https://api.github.com/user');

            if (!$response->successful()) {
                Log::warning('GitHub token verification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }

            $user = $response->json();

            // If email is null, try to get primary verified email
            $email = $user['email'];
            if (!$email) {
                $emailResponse = Http::withToken($accessToken)
                    ->get('https://api.github.com/user/emails');

                if ($emailResponse->successful()) {
                    $emails = $emailResponse->json();
                    // Find primary verified email
                    foreach ($emails as $emailData) {
                        if ($emailData['primary'] && $emailData['verified']) {
                            $email = $emailData['email'];
                            break;
                        }
                    }
                }
            }

            // Return user data in Socialite-like format
            return (object) [
                'id' => (string) $user['id'],
                'email' => $email,
                'name' => $user['name'] ?? $user['login'],
                'nickname' => $user['login'],
                'avatar' => $user['avatar_url'] ?? null,
                'token' => $accessToken,
                'refreshToken' => null,
            ];
        } catch (\Exception $e) {
            Log::error('GitHub token verification exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate OAuth provider.
     */
    protected function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'github'])) {
            abort(404, 'OAuth provider not supported');
        }
    }

    /**
     * Check if the request is for token-based authentication.
     */
    protected function isTokenRequest(Request $request): bool
    {
        return $request->hasHeader('X-Request-Token');
    }

    /**
     * Check if user has a password set.
     * OAuth-only users have null passwords.
     * Users who set/reset their password will have a non-null password.
     */
    protected function userHasKnownPassword(User $user): bool
    {
        return $user->password !== null;
    }

    /**
     * Ensure social authentication is enabled.
     */
    protected function ensureSocialAuthEnabled(Request $request): RedirectResponse|JsonResponse|null
    {
        if (!Config::get('auth.social_auth_enabled', true)) {
            $message = 'Social authentication is disabled.';

            if ($this->isTokenRequest($request)) {
                return $this->sendError($message, 403);
            }

            // If user is authenticated (linking scenario), redirect to settings
            // Otherwise redirect to login page (login scenario)
            if (Auth::check()) {
                return redirect($this->getFrontendSettingsUrl() . '&error=' . urlencode($message));
            }

            return redirect($this->getFrontendLoginUrl() . '?error=' . urlencode($message));
        }

        return null;
    }

    /**
     * Download avatar from URL and store it locally.
     *
     * @param string $avatarUrl
     * @param string $provider
     * @return string|null Returns the stored file path or null if failed
     */
    protected function downloadAvatarFromUrl(string $avatarUrl, string $provider): ?string
    {
        try {
            // Download the image from the URL
            $response = Http::timeout(10)->get($avatarUrl);

            if (!$response->successful()) {
                Log::warning('Failed to download avatar from social provider', [
                    'provider' => $provider,
                    'url' => $avatarUrl,
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Get the image content
            $imageContent = $response->body();

            // Validate image size (limit to 5MB)
            if (strlen($imageContent) > 5 * 1024 * 1024) {
                Log::warning('Avatar too large from social provider', [
                    'provider' => $provider,
                    'size' => strlen($imageContent),
                ]);
                return null;
            }

            // Detect the file extension from the content type
            $contentType = $response->header('Content-Type');
            $extension = $this->getExtensionFromMimeType($contentType);

            if (!$extension) {
                Log::warning('Invalid avatar content type from social provider', [
                    'provider' => $provider,
                    'content_type' => $contentType,
                ]);
                return null;
            }

            // Generate a unique filename
            $filename = 'avatars/' . uniqid('avatar_' . $provider . '_', true) . '.' . $extension;

            // Store the image
            Storage::disk('public')->put($filename, $imageContent);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Exception while downloading avatar from social provider', [
                'provider' => $provider,
                'url' => $avatarUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Download and set avatar for user if they don't have one.
     *
     * @param User $user
     * @param object $socialUser
     * @return void
     */
    protected function downloadAvatarIfNeeded(User $user, $socialUser): void
    {
        // Only download if user doesn't have an avatar and social provider has one
        if (!$user->avatar && !empty($socialUser->avatar)) {
            $avatarPath = $this->downloadAvatarFromUrl($socialUser->avatar, 'social');
            if ($avatarPath) {
                $user->avatar = $avatarPath;
                $user->save();
            }
        }
    }

    /**
     * Get file extension from MIME type.
     *
     * @param string|null $mimeType
     * @return string|null
     */
    protected function getExtensionFromMimeType(?string $mimeType): ?string
    {
        if (!$mimeType) {
            return null;
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $mimeMap[$mimeType] ?? null;
    }
}
