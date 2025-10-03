<?php

namespace App\Repositories;

use Str;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Notifications\EmailChangeVerification;
use App\Notifications\EmailChangeSuccess;
use App\Notifications\EmailChangeAlert;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class UserRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'email',
        'phone',
        'email_verified_at',
        'password'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return User::class;
    }

    public function getFilter($filters)
    {
        return $this->model->filter($filters);
    }

    /**
     * Get all users with their associated roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>
     */
    public function getAll()
    {
        return $this->model->with('role')->get();
    }

    /**
     * Create a new user with the given inputs.
     *
     * @param array $inputs Array containing user data with keys:
     *                     - name: string User's name
     *                     - email: string User's email
     *                     - phone: string User's phone number
     *                     - role_id: int Role ID to assign
     * @return User The newly created user model
     */
    public function createNewUser(array $inputs): User
    {
        // Create user with role
        $user = $this->model->create([
            'name' => $inputs['name'],
            'email' => $inputs['email'],
            'password' => Hash::make(Str::random(8)),
            'phone' => $inputs['phone'],
            'role_id' => $inputs['role_id'],
        ]);

        // Send password reset notification with extended expiration for new users
        // Note: No throttling check for new user creation as this is the first password reset request
        $this->sendExtendedPasswordReset($user);

        return $user;
    }

    /**
     * Update a user's password.
     *
     * @param int $id The ID of the user to update
     * @param string $password The new password to set
     * @return void
     */
    public function updateUserPassword(int $id, string $password): void
    {
        $user = $this->model->find($id);

        $user->password = Hash::make($password);

        $user->save();
    }

    /**
     * Delete a user with validation.
     *
     * @param int $idToDelete ID of the user to delete
     * @param int $currentUserId ID of the authenticated user
     * @return array Associative array with result and message
     */
    public function destroyUser(int $idToDelete, int $currentUserId): array
    {
        // Get user to delete
        $userToDelete = $this->find($idToDelete);

        // Check if user exists
        if (!$userToDelete) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        // Check if user is trying to delete themselves
        if ($currentUserId == $userToDelete->id) {
            return [
                'success' => false,
                'message' => 'You cannot delete yourself.',
                'status' => 400
            ];
        }

        // Delete user
        $this->delete($idToDelete);

        return [
            'success' => true,
            'message' => 'User deleted successfully',
            'status' => 200
        ];
    }

    /**
     * Delete multiple users by IDs.
     *
     * @param array $ids Array of user IDs to delete
     * @param int $currentUserId ID of the authenticated user
     * @return array Associative array with results
     */
    public function bulkDestroy(array $ids, int $currentUserId): array
    {
        $result = [
            'deleted' => 0,
            'failed' => 0,
            'attempted' => count($ids),
            'self_delete_attempt' => false
        ];

        foreach ($ids as $id) {
            // Skip if user is trying to delete themselves
            if ($id == $currentUserId) {
                $result['self_delete_attempt'] = true;
                $result['failed']++;
                continue;
            }

            try {
                $this->delete($id);
                $result['deleted']++;
            } catch (\Exception $e) {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Update user avatar.
     *
     * @param int $userId
     * @param UploadedFile $avatar
     * @return array
     */
    public function updateAvatar(int $userId, UploadedFile $avatar): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        try {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $avatar->store('avatars', 'public');

            // Update user avatar path
            $user->avatar = $avatarPath;
            $user->save();

            return [
                'success' => true,
                'message' => 'Avatar updated successfully.',
                'avatar_url' => Storage::disk('public')->url($avatarPath),
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to upload avatar: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Delete user avatar.
     *
     * @param int $userId
     * @return array
     */
    public function deleteAvatar(int $userId): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        try {
            // Delete avatar file if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Remove avatar path from user
            $user->avatar = null;
            $user->save();

            return [
                'success' => true,
                'message' => 'Avatar deleted successfully.',
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete avatar: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Resend password reset link for a user.
     *
     * @param int $userId
     * @return array
     */
    public function resendPasswordResetLink(int $userId): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        try {
            // Check for throttling before sending
            if ($this->isPasswordResetThrottled($user->email)) {
                return [
                    'success' => false,
                    'message' => 'Too many password reset attempts. Please wait before trying again.',
                    'status' => 429
                ];
            }

            // Send password reset notification with extended expiration
            $this->sendExtendedPasswordReset($user);

            return [
                'success' => true,
                'message' => 'Password reset link sent successfully.',
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send password reset link: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Send a password reset notification with extended expiration.
     *
     * @param User $user
     * @return void
     */
    private function sendExtendedPasswordReset(User $user): void
    {
        // Generate a secure random token
        $token = Str::random(60);

        // Store the token in the default password_reset_tokens table
        DB::table(config('auth.passwords.users.table'))->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send the custom notification with extended expiration message
        $user->sendExtendedPasswordResetNotification($token);
    }

    /**
     * Check if password reset is throttled for the given email.
     *
     * @param string $email
     * @return bool
     */
    private function isPasswordResetThrottled(string $email): bool
    {
        $throttleMinutes = config('auth.passwords.users.throttle', 60);

        $recentToken = DB::table(config('auth.passwords.users.table'))
            ->where('email', $email)
            ->where('created_at', '>', now()->subSeconds($throttleMinutes))
            ->first();

        return $recentToken !== null;
    }

    /**
     * Request an email change for a user.
     *
     * @param int $userId
     * @param string $newEmail
     * @param bool $useApiRoute
     * @return array
     */
    public function requestEmailChange(int $userId, string $newEmail, bool $useApiRoute = false): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        try {
            // Check for throttling - prevent multiple requests within 5 minutes
            if ($this->isEmailChangeThrottled($user->id)) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting another email change.',
                    'status' => 429
                ];
            }

            // Generate a secure token
            $token = Str::random(60);

            // Update user with pending email change
            $user->pending_email = $newEmail;
            $user->email_change_token = Hash::make($token);
            $user->email_change_requested_at = now();
            $user->save();

            // Send verification notification to the new email
            $user->notify(new EmailChangeVerification($token, $newEmail, $useApiRoute));

            return [
                'success' => true,
                'message' => 'Email change verification sent to your new email address.',
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send email change verification: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Verify and complete an email change.
     *
     * @param int $userId
     * @param string $token
     * @param string $newEmail
     * @return array
     */
    public function verifyEmailChange(int $userId, string $token, string $newEmail): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        try {
            // Verify the token and email match
            if (
                !$user->pending_email ||
                !$user->email_change_token ||
                !Hash::check($token, $user->email_change_token) ||
                $user->pending_email !== $newEmail
            ) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification token.',
                    'status' => 400
                ];
            }

            // Check if token hasn't expired
            $expireTime = config('auth.verification.expire', 60);
            if ($user->email_change_requested_at->addMinutes($expireTime)->isPast()) {
                // Clean up expired request
                $this->clearEmailChangeRequest($user->id);
                return [
                    'success' => false,
                    'message' => 'Verification token has expired.',
                    'status' => 400
                ];
            }

            // Store old email before changing
            $oldEmail = $user->email;
            $userName = $user->name;
            $changedAt = now()->format('F j, Y \a\t g:i A'); // Capture exact time of change

            // Update user's email
            $user->email = $newEmail;
            $user->email_verified_at = now(); // Mark new email as verified

            // Clear pending email change data
            $user->pending_email = null;
            $user->email_change_token = null;
            $user->email_change_requested_at = null;

            $user->save();

            // Send email notifications (queued for background processing)
            // Send success notification to NEW email address (user's current email)
            $user->notify(new EmailChangeSuccess($oldEmail));

            // Send security alert to OLD email address with slight delay (using anonymous notifiable)
            Notification::route('mail', $oldEmail)
                ->notify((new EmailChangeAlert($oldEmail, $newEmail, $userName, $changedAt))->delay(now()->addSeconds(60)));

            return [
                'success' => true,
                'message' => 'Email address updated successfully.',
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update email address: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to update email address: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Cancel a pending email change request.
     *
     * @param int $userId
     * @return array
     */
    public function cancelEmailChange(int $userId): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        if (!$user->pending_email) {
            return [
                'success' => false,
                'message' => 'No pending email change found.',
                'status' => 400
            ];
        }

        try {
            $this->clearEmailChangeRequest($user->id);

            return [
                'success' => true,
                'message' => 'Email change request cancelled successfully.',
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to cancel email change: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Resend email change verification to the pending email address.
     *
     * @param int $userId
     * @param bool $useApiRoute
     * @return array
     */
    public function resendEmailChangeVerification(int $userId, bool $useApiRoute = false): array
    {
        $user = $this->find($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'status' => 404
            ];
        }

        if (!$user->pending_email || !$user->email_change_token) {
            return [
                'success' => false,
                'message' => 'No pending email change found.',
                'status' => 400
            ];
        }

        try {
            // Check if token hasn't expired
            $expireTime = config('auth.verification.expire', 60);
            if ($user->email_change_requested_at->addMinutes($expireTime)->isPast()) {
                // Clean up expired request
                $this->clearEmailChangeRequest($user->id);
                return [
                    'success' => false,
                    'message' => 'Verification token has expired. Please request a new email change.',
                    'status' => 400
                ];
            }

            // Generate a new secure token
            $token = Str::random(60);

            // Update the token and timestamp
            $user->email_change_token = Hash::make($token);
            $user->email_change_requested_at = now();
            $user->save();

            // Resend verification notification to the pending email
            $user->notify(new EmailChangeVerification($token, $user->pending_email, $useApiRoute));

            return [
                'success' => true,
                'message' => 'Email change verification sent successfully.',
                'status' => 200
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to resend email change verification: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * Check if email change is throttled for the given user.
     *
     * @param int $userId
     * @return bool
     */
    private function isEmailChangeThrottled(int $userId): bool
    {
        $throttleMinutes = 5; // 5 minutes throttle for email change requests

        $user = $this->find($userId);

        if (!$user || !$user->email_change_requested_at) {
            return false;
        }

        return $user->email_change_requested_at->addMinutes($throttleMinutes)->isFuture();
    }

    /**
     * Clear email change request data for a user.
     *
     * @param int $userId
     * @return void
     */
    private function clearEmailChangeRequest(int $userId): void
    {
        $user = $this->find($userId);

        if ($user) {
            $user->pending_email = null;
            $user->email_change_token = null;
            $user->email_change_requested_at = null;
            $user->save();
        }
    }

    /**
     * Clean up expired email change requests.
     *
     * @return int Number of cleaned up requests
     */
    public function cleanupExpiredEmailChangeRequests(): int
    {
        $expireTime = config('auth.verification.expire', 60);
        $expiredTime = now()->subMinutes($expireTime);

        return $this->model
            ->whereNotNull('email_change_requested_at')
            ->where('email_change_requested_at', '<', $expiredTime)
            ->update([
                'pending_email' => null,
                'email_change_token' => null,
                'email_change_requested_at' => null,
            ]);
    }
}
